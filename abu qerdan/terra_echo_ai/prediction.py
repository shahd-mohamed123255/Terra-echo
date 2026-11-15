from __future__ import annotations

from typing import Any


def _linear_fit(points: list[tuple[float, float]]) -> tuple[float, float]:
    mean_x = sum(x for x, _ in points) / len(points)
    mean_y = sum(y for _, y in points) / len(points)
    denominator = sum((x - mean_x) ** 2 for x, _ in points)
    if denominator == 0:
        return mean_y, 0.0
    slope = sum((x - mean_x) * (y - mean_y) for x, y in points) / denominator
    return mean_y - slope * mean_x, slope


def _mae(actual: list[float], predicted: list[float]) -> float:
    return sum(abs(left - right) for left, right in zip(actual, predicted)) / len(actual)


def predict_next(
    records: list[dict[str, Any]], target: str, date_column: str | None = None
) -> dict[str, Any]:
    if len(records) < 8:
        return {
            "available": False,
            "reason": "At least 8 valid historical observations are required for a chronological train/test evaluation.",
            "valid_observations": len(records),
        }
    candidates = []
    for index, record in enumerate(records):
        value = record.get(target)
        if isinstance(value, bool):
            continue
        try:
            numeric_value = float(value)
        except (TypeError, ValueError):
            continue
        if date_column and record.get(date_column):
            candidates.append((str(record[date_column]), numeric_value))
        else:
            candidates.append((str(index), numeric_value))
    if len(candidates) < 8:
        return {
            "available": False,
            "reason": "The selected target contains fewer than 8 valid numeric observations.",
            "valid_observations": len(candidates),
        }
    candidates.sort(key=lambda item: item[0])
    values = [value for _, value in candidates]
    split = max(6, int(len(values) * 0.8))
    split = min(split, len(values) - 2)
    train = [(float(index), value) for index, value in enumerate(values[:split])]
    intercept, slope = _linear_fit(train)
    test_actual = values[split:]
    test_linear = [intercept + slope * index for index in range(split, len(values))]
    baseline = [values[split - 1]] * len(test_actual)
    linear_mae = _mae(test_actual, test_linear)
    baseline_mae = _mae(test_actual, baseline)
    all_points = [(float(index), value) for index, value in enumerate(values)]
    final_intercept, final_slope = _linear_fit(all_points)
    next_value = final_intercept + final_slope * len(values)
    return {
        "available": True,
        "model": "ordinary_least_squares_linear_trend",
        "target": target,
        "training_observations": split,
        "evaluation_observations": len(test_actual),
        "training_period": {"start": candidates[0][0], "end": candidates[split - 1][0]},
        "evaluation_period": {"start": candidates[split][0], "end": candidates[-1][0]},
        "evaluation_metrics": {
            "mae": round(linear_mae, 4),
            "previous_value_baseline_mae": round(baseline_mae, 4),
            "beats_previous_value_baseline": linear_mae < baseline_mae,
        },
        "prediction": {
            "horizon": "one observation step",
            "value": round(next_value, 4),
            "time_index": candidates[-1][0],
        },
        "uncertainty": "No prediction interval is estimated; the short historical series may not represent future conditions.",
        "data_quality_note": "Chronological holdout used; no random shuffling. Verify timestamps and units before interpreting.",
    }
