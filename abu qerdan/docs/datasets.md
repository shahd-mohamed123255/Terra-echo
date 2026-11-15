# Project datasets and dataset addition guide

## Files already in the workspace

| File | Shape and coverage | Meaning and use | Relationship / limitations |
|---|---|---|---|
| `terra_echo_ml_clean_8zones.csv` | 3,745 monthly crop-zone rows, 62 columns; 8 named Egyptian zones; April 2015–June 2025; 8 crop labels; winter/summer seasons. | Primary local crop-season evidence. The UI filters by `zone`, `crop`, `season`, and `season_complete`, groups monthly rows by `harvest_year`, and reports historical averages for temperature, precipitation, ET0, crop ETc/irrigation, NDVI, and soil properties. | `crop_irrigation_mm`, crop ETc, Kc, NDVI, soil variables, and crop requirement columns are already present; they are not independent farm measurements just because they are columns. The file has no supplied data dictionary/provenance metadata. Units are suggested by field names and must be verified before operational use. `season_complete=1` is used to avoid treating partial crop seasons as complete. |
| `fao_national_yield_by_crop_year.csv` | 66 records; seven crops; harvest years 2015–2024. Columns: `crop` (text), `harvest_year` (integer-like year), `national_area_ha` (numeric), `national_yield_t_ha` (numeric), `faostat_flag` (categorical source flag). | National-area and national-yield historical context. The chatbot can retrieve annual crop yield values and request a chronological one-step linear-trend baseline through the existing prediction endpoint. | 65 of 66 rows have at least one exact `crop + harvest_year` match in the monthly dataset. Only 62 have a complete crop-season profile under the app's `season_complete=1` filter; four 2015 crop-year rows do not. This is a temporal/category alignment, not a farm-level join. Yield is national, and the flag meanings are not documented in this workspace; neither should be presented as field observations. |
| `data/sample/farm_summary.json` | One JSON object. Includes illustrative farm ID, location, 10 ha, clay soil, wheat, crop history, planting date, analysis period and priorities. | Default editable UI context and existing Agent API test input. The Planning page chooses the nearest available zone from its coordinates. | Explicitly marked illustrative in `_note`; not a verified farmer record. `irrigation` is `null`, so the UI does not fabricate available irrigation. |
| `data/sample/weather_observations.csv` | 8 daily rows, 5 columns: date, temperature °C, precipitation mm, reference ET0 mm, and source. Dates: 2026-01-01–2026-01-08. | Example shape for local weather ingestion only. | Every row's source is `illustrative_test_only`; it is not joined to the crop dataset or used as observed weather in the app. It is also too short for a meaningful seasonal climate estimate. |

The supplied CSV rows have no blank cells. The monthly file has unique `zone + crop + month_start` keys in the inspected data; this does not establish that the source data is free of other quality or provenance issues.

### Crop-zone dataset field groups

Field groups inferred from names and values (confirm units and source provenance before scientific use):

- Keys/calendar: `zone`, `lat`, `lon`, `month_start`, `month`, `year`, `month_num`, `season`, `season_start_year`, `harvest_year`, `season_complete`, `season_months_expected`, `crop`.
- Crop-water calculations: `kc_draft`, `crop_etc_mm`, `crop_eff_rain_mm`, `crop_irrigation_mm`, `crop_irrigation_low_mm`, `et_crop_mm_day`, `et_crop_mm_month`, `et0_mm_day`, `et0_mm_month`, `et0_hargreaves_mm_day`.
- Weather/climate: `t2m_mean`, `t2m_max_mean`, `t2m_min_mean`, `t2m_max_abs`, `t2m_min_abs`, `precip_mm`, `rh2m_mean`, `solar_mj_m2_day`, `wind_ms_mean`, `days_in_data`, `frost_days`, `heat_days_40c`.
- Earth-observation / vegetation / soil: `lst_day_c_pointbased`, `lst_night_c_pointbased`, `ndvi_crop`, `cropland_fraction_2023`, `soil_ph`, `soil_clay_pct`, `soil_sand_pct`, `soil_soc_g_kg`, `smap_sm_surface_m3m3`, `smap_sm_rootzone_m3m3`.
- Crop requirement fields: `req_temp_*`, `req_ph_*`, `req_salinity_*`, `req_cycle_*`, `req_rain_annual_*`, and `req_salinity_abs_max_is_lower_bound`. These are crop-screening reference values embedded in rows, not observed field outcomes.

The current UI joins the two principal datasets only by `crop` and `harvest_year` for contextual display; it does not merge national yield onto local monthly rows or claim the resulting association is causal. The planning profile groups complete monthly records by `zone + crop + season + harvest_year`; rainfall, ET0, crop ETc and irrigation amounts are summed across the grouped months, while temperatures, NDVI, Kc, and soil properties are averaged. Averages across those seasonal groups are explicitly historical summaries.

Put files in `data/incoming/` and use `POST /api/agent/data/inspect` before ingestion. The inspection response lists columns/variables, likely units, potential timestamp columns, missing-value counts, duplicate rows, and invalid timestamps. Unknown fields are retained; guessed unit hints are explicitly marked as needing confirmation.

Then call `POST /api/agent/data/ingest`. The raw input is retained in `data/raw/<category>/`, tabular data is normalized to JSON under `data/processed/<category>/`, and SQLite records the dataset ID, source, schema, status, and metadata. Use `GET /api/agent/data/catalog` to discover registered data. Dataset IDs are content-derived so identical files update the same registry entry.

CSV, JSON, and GeoJSON readers use the Python standard library. Optional readers:

| Format | Optional packages |
|---|---|
| Excel | `openpyxl` |
| Parquet | `pandas`, `pyarrow` |
| NetCDF | `xarray` |
| GeoTIFF | `rasterio` |

Install only the reader packages needed by the supplied datasets. NetCDF/GeoTIFF are registered with variable/spatial metadata; the service does not invent tabular samples or claim those data have been connected to a scientific analysis. Inspect units, CRS, nodata, time axis, coverage, spatial/temporal resolution, and provenance before using a new source in decisions.
