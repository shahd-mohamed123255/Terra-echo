from __future__ import annotations

import csv
import hashlib
import importlib
import json
import shutil
import statistics
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from terra_echo_ai.config import DATA_DIR
from terra_echo_ai.database import database, json_dump


SUPPORTED_EXTENSIONS = {
    ".csv": "csv",
    ".json": "json",
    ".geojson": "geojson",
    ".xlsx": "excel",
    ".xls": "excel",
    ".parquet": "parquet",
    ".nc": "netcdf",
    ".netcdf": "netcdf",
    ".tif": "geotiff",
    ".tiff": "geotiff",
}


def _read_table(path: Path, data_format: str) -> tuple[list[str], list[dict[str, Any]], dict[str, Any]]:
    if data_format == "csv":
        with path.open("r", encoding="utf-8-sig", newline="") as file:
            reader = csv.DictReader(file)
            columns = list(reader.fieldnames or [])
            rows = [dict(row) for row in reader]
        return columns, rows, {}
    if data_format in {"json", "geojson"}:
        content = json.loads(path.read_text(encoding="utf-8"))
        if data_format == "geojson" or (
            isinstance(content, dict) and content.get("type") == "FeatureCollection"
        ):
            features = content.get("features") if isinstance(content, dict) else None
            if not isinstance(features, list):
                raise ValueError("GeoJSON must contain a FeatureCollection.features array")
            rows = [
                {
                    **(feature.get("properties") or {}),
                    "geometry": feature.get("geometry"),
                }
                for feature in features
            ]
            return sorted({key for row in rows for key in row}), rows, {
                "spatial_type": "GeoJSON FeatureCollection"
            }
        rows = content if isinstance(content, list) else content.get("data")
        if isinstance(rows, list) and all(isinstance(row, dict) for row in rows):
            return sorted({key for row in rows for key in row}), rows, {}
        if isinstance(content, dict):
            return list(content.keys()), [content], {}
        raise ValueError("JSON must contain an object, an array of objects, or a data array")
    if data_format == "excel":
        try:
            module = importlib.import_module("openpyxl")
        except ImportError as error:
            raise ValueError("Excel inspection requires the optional 'openpyxl' package") from error
        workbook = module.load_workbook(path, read_only=True, data_only=True)
        worksheet = workbook.active
        values = worksheet.iter_rows(values_only=True)
        headers = [str(value) for value in next(values, ())]
        rows = [
            dict(zip(headers, row))
            for row in values
            if any(value is not None for value in row)
        ]
        return headers, rows, {"sheet": worksheet.title}
    if data_format == "parquet":
        try:
            pandas = importlib.import_module("pandas")
        except ImportError as error:
            raise ValueError("Parquet inspection requires the optional 'pandas' and 'pyarrow' packages") from error
        frame = pandas.read_parquet(path)
        return list(frame.columns), frame.head(5000).to_dict(orient="records"), {
            "row_count": int(len(frame)),
            "sampled_rows": min(int(len(frame)), 5000),
        }
    if data_format == "netcdf":
        try:
            xarray = importlib.import_module("xarray")
        except ImportError as error:
            raise ValueError("NetCDF inspection requires the optional 'xarray' package") from error
        with xarray.open_dataset(path) as dataset:
            return list(dataset.data_vars), [], {
                "dimensions": dict(dataset.sizes),
                "variables": {
                    name: {
                        "dimensions": list(variable.dims),
                        "units": variable.attrs.get("units"),
                        "long_name": variable.attrs.get("long_name"),
                    }
                    for name, variable in dataset.data_vars.items()
                },
                "coordinates": list(dataset.coords),
            }
    if data_format == "geotiff":
        try:
            rasterio = importlib.import_module("rasterio")
        except ImportError as error:
            raise ValueError("GeoTIFF inspection requires the optional 'rasterio' package") from error
        with rasterio.open(path) as raster:
            return [f"band_{index}" for index in range(1, raster.count + 1)], [], {
                "crs": str(raster.crs) if raster.crs else None,
                "bounds": list(raster.bounds),
                "resolution": list(raster.res),
                "width": raster.width,
                "height": raster.height,
                "band_count": raster.count,
                "nodata": raster.nodata,
            }
    raise ValueError(f"Unsupported dataset format: {data_format}")


def _quality(columns: list[str], rows: list[dict[str, Any]]) -> dict[str, Any]:
    duplicates = 0
    seen: set[str] = set()
    for row in rows:
        key = json.dumps(row, sort_keys=True, default=str)
        if key in seen:
            duplicates += 1
        seen.add(key)
    missing = {
        column: sum(row.get(column) is None or row.get(column) == "" for row in rows)
        for column in columns
    }
    timestamps = [
        column
        for column in columns
        if any(term in column.lower() for term in ("date", "time", "timestamp"))
    ]
    invalid_timestamps: dict[str, int] = {}
    for column in timestamps:
        invalid_timestamps[column] = sum(
            not _is_parseable_date(row.get(column)) for row in rows if row.get(column) not in (None, "")
        )
    return {
        "row_count": len(rows),
        "duplicate_rows": duplicates,
        "missing_values_by_column": missing,
        "timestamp_columns": timestamps,
        "invalid_timestamps_by_column": invalid_timestamps,
    }


def _is_parseable_date(value: Any) -> bool:
    if isinstance(value, datetime):
        return True
    if not isinstance(value, str):
        return False
    try:
        datetime.fromisoformat(value.replace("Z", "+00:00"))
        return True
    except ValueError:
        try:
            datetime.strptime(value, "%Y%m%d")
            return True
        except ValueError:
            return False


def _infer_units(columns: list[str]) -> dict[str, str | None]:
    known_units = {
        "temperature": "degC or degF (confirm source metadata)",
        "precipitation": "mm (confirm source metadata)",
        "rainfall": "mm (confirm source metadata)",
        "soil_moisture": "source-dependent (confirm metadata)",
        "ndvi": "unitless index",
        "latitude": "decimal degrees",
        "longitude": "decimal degrees",
    }
    inferred: dict[str, str | None] = {}
    for column in columns:
        key = column.lower()
        inferred[column] = next(
            (unit for marker, unit in known_units.items() if marker in key),
            None,
        )
    return inferred


def _parsed_date(value: Any) -> datetime | None:
    if isinstance(value, datetime):
        parsed = value
    elif isinstance(value, str):
        try:
            parsed = datetime.fromisoformat(value.replace("Z", "+00:00"))
        except ValueError:
            try:
                parsed = datetime.strptime(value, "%Y%m%d")
            except ValueError:
                return None
    else:
        return None
    if parsed.tzinfo is None:
        return parsed.replace(tzinfo=timezone.utc)
    return parsed.astimezone(timezone.utc)


def _spatial_coverage(
    columns: list[str], rows: list[dict[str, Any]], details: dict[str, Any]
) -> dict[str, Any] | None:
    if details.get("bounds"):
        return {"bounds": details["bounds"], "crs": details["crs"]}
    normalized = {column.lower(): column for column in columns}
    latitude = normalized.get("latitude") or normalized.get("lat")
    longitude = normalized.get("longitude") or normalized.get("lon")
    if latitude and longitude:
        points = []
        for row in rows:
            try:
                points.append((float(row[longitude]), float(row[latitude])))
            except (TypeError, ValueError, KeyError):
                continue
        if points:
            return {
                "bounds_lon_lat": [
                    min(point[0] for point in points),
                    min(point[1] for point in points),
                    max(point[0] for point in points),
                    max(point[1] for point in points),
                ],
                "crs": "WGS84 assumed from latitude/longitude column names; verify source metadata",
            }
    coordinate_pairs: list[tuple[float, float]] = []

    def collect_pairs(coordinates: Any) -> None:
        if not isinstance(coordinates, list):
            return
        if len(coordinates) >= 2 and all(
            isinstance(value, (int, float)) and not isinstance(value, bool)
            for value in coordinates[:2]
        ):
            coordinate_pairs.append((float(coordinates[0]), float(coordinates[1])))
            return
        for value in coordinates:
            collect_pairs(value)

    for row in rows:
        geometry = row.get("geometry")
        if isinstance(geometry, dict):
            collect_pairs(geometry.get("coordinates"))
    if coordinate_pairs:
        return {
            "bounds_lon_lat": [
                min(point[0] for point in coordinate_pairs),
                min(point[1] for point in coordinate_pairs),
                max(point[0] for point in coordinate_pairs),
                max(point[1] for point in coordinate_pairs),
            ],
            "crs": "GeoJSON default CRS per RFC 7946 (WGS84)",
        }
    return None


def _temporal_coverage(
    columns: list[str], rows: list[dict[str, Any]]
) -> tuple[dict[str, str] | None, str | None]:
    date_columns = [
        column
        for column in columns
        if any(term in column.lower() for term in ("date", "time", "timestamp"))
    ]
    for column in date_columns:
        values = sorted(
            parsed
            for row in rows
            if (parsed := _parsed_date(row.get(column))) is not None
        )
        if not values:
            continue
        unique_values = sorted(set(values))
        resolution = "irregular"
        if len(unique_values) > 1:
            differences = [
                (right - left).total_seconds() / 86400
                for left, right in zip(unique_values, unique_values[1:])
            ]
            median_days = statistics.median(differences)
            resolution = (
                "daily"
                if median_days == 1
                else f"approximately {median_days:g} days (inferred)"
            )
        return (
            {
                "start": values[0].isoformat(),
                "end": values[-1].isoformat(),
                "column": column,
            },
            resolution,
        )
    return None, None


def _allowed_input(path: Path) -> Path:
    incoming = (DATA_DIR / "incoming").resolve()
    resolved = path.resolve()
    if not resolved.is_relative_to(incoming):
        raise ValueError(f"Input files must be placed under {incoming}")
    if not resolved.is_file():
        raise ValueError(f"Dataset file does not exist: {resolved}")
    return resolved


def inspect_dataset(file_path: str | Path) -> dict[str, Any]:
    path = _allowed_input(Path(file_path))
    data_format = SUPPORTED_EXTENSIONS.get(path.suffix.lower())
    if data_format is None:
        raise ValueError(f"Unsupported file extension: {path.suffix}")
    columns, rows, details = _read_table(path, data_format)
    temporal_coverage, temporal_resolution = _temporal_coverage(columns, rows)
    metadata = {
        "format": data_format,
        "columns_or_variables": columns,
        "units": _infer_units(columns),
        "details": details,
        "quality": _quality(columns, rows),
        "spatial_coverage": _spatial_coverage(columns, rows, details),
        "temporal_coverage": temporal_coverage,
        "spatial_resolution": details.get("resolution"),
        "temporal_resolution": temporal_resolution,
    }
    return {
        "file_name": path.name,
        "data_format": data_format,
        "columns_or_variables": columns,
        "metadata": metadata,
        "warnings": [
            "Unknown fields are retained and are not assigned an assumed meaning."
        ],
    }


def ingest_dataset(
    file_path: str | Path,
    name: str | None = None,
    source: str | None = None,
    category: str = "agricultural",
) -> dict[str, Any]:
    path = _allowed_input(Path(file_path))
    inspected = inspect_dataset(path)
    data_format = inspected["data_format"]
    digest = hashlib.sha256()
    with path.open("rb") as file:
        for chunk in iter(lambda: file.read(1024 * 1024), b""):
            digest.update(chunk)
    content_hash = digest.hexdigest()
    dataset_id = f"ds_{content_hash[:20]}"
    category = "".join(character for character in category.lower() if character.isalnum() or character in "-_")
    raw_dir = DATA_DIR / "raw" / category
    processed_dir = DATA_DIR / "processed" / category
    raw_dir.mkdir(parents=True, exist_ok=True)
    processed_dir.mkdir(parents=True, exist_ok=True)
    raw_path = raw_dir / path.name
    if path.resolve() != raw_path.resolve():
        shutil.copy2(path, raw_path)
    metadata = inspected["metadata"]
    metadata.update(
        {
            "dataset_id": dataset_id,
            "source": source,
            "processing_status": "registered_raw",
            "validation_status": "warnings"
            if (
                not inspected["columns_or_variables"]
                or metadata["quality"]["row_count"] == 0
                or metadata["quality"]["duplicate_rows"]
                or any(metadata["quality"]["missing_values_by_column"].values())
                or any(metadata["quality"]["invalid_timestamps_by_column"].values())
            )
            else "passed",
            "known_limitations": ["Units and semantics not present in the source remain unverified."],
        }
    )
    if data_format in {"csv", "json", "geojson", "excel", "parquet"}:
        columns, rows, _ = _read_table(raw_path, data_format)
        normalized_path = processed_dir / f"{dataset_id}.json"
        normalized_path.write_text(
            json.dumps(
                {"columns": columns, "records": rows, "metadata": metadata},
                ensure_ascii=False,
                default=str,
                indent=2,
            ),
            encoding="utf-8",
        )
        metadata["processed_file_path"] = str(normalized_path)
    with database() as connection:
        connection.execute(
            """
            INSERT INTO datasets (
                dataset_id, name, source, file_path, data_type, schema_json,
                metadata_json, validation_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(dataset_id) DO UPDATE SET
                name=excluded.name,
                source=excluded.source,
                file_path=excluded.file_path,
                data_type=excluded.data_type,
                schema_json=excluded.schema_json,
                metadata_json=excluded.metadata_json,
                validation_status=excluded.validation_status
            """,
            (
                dataset_id,
                name or path.stem,
                source,
                str(raw_path),
                data_format,
                json_dump(inspected["columns_or_variables"]),
                json_dump(metadata),
                metadata["validation_status"],
            ),
        )
    return {
        "dataset_id": dataset_id,
        "name": name or path.stem,
        "source": source,
        "data_type": data_format,
        "file_path": str(raw_path),
        "metadata": metadata,
    }


def list_datasets() -> list[dict[str, Any]]:
    with database() as connection:
        rows = connection.execute(
            "SELECT dataset_id, name, source, file_path, data_type, schema_json, metadata_json, validation_status, created_at FROM datasets ORDER BY created_at DESC"
        ).fetchall()
    return [
        {
            **dict(row),
            "variables": json.loads(row["schema_json"]),
            "metadata": json.loads(row["metadata_json"]),
        }
        for row in rows
    ]


def dataset_records(dataset_id: str) -> list[dict[str, Any]]:
    with database() as connection:
        row = connection.execute(
            "SELECT metadata_json FROM datasets WHERE dataset_id = ?", (dataset_id,)
        ).fetchone()
    if row is None:
        raise ValueError(f"Unknown dataset_id: {dataset_id}")
    processed_path = json.loads(row["metadata_json"]).get("processed_file_path")
    if not processed_path:
        raise ValueError("This scientific raster/cube dataset has metadata but no tabular records.")
    content = json.loads(Path(processed_path).read_text(encoding="utf-8"))
    records = content.get("records")
    if not isinstance(records, list):
        raise ValueError("Processed dataset does not contain tabular records.")
    return records
