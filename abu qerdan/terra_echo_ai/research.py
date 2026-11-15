from __future__ import annotations

import json
import os
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen
from typing import Any


class SearchUnavailable(RuntimeError):
    pass


def search_external(query: str, limit: int = 5) -> dict[str, Any]:
    endpoint = os.getenv("TERRA_ECHO_SEARCH_URL")
    if not endpoint:
        return {
            "available": False,
            "reason": "External search is not configured; no live web search was performed.",
            "results": [],
        }
    url = f"{endpoint}?{urlencode({'q': query, 'limit': limit})}"
    headers = {"Accept": "application/json"}
    api_key = os.getenv("TERRA_ECHO_SEARCH_API_KEY")
    if api_key:
        headers["Authorization"] = f"Bearer {api_key}"
    request = Request(url, headers=headers)
    try:
        with urlopen(request, timeout=15) as response:
            payload = json.loads(response.read().decode("utf-8"))
    except (HTTPError, URLError, TimeoutError, json.JSONDecodeError) as error:
        raise SearchUnavailable(f"Configured external search failed: {error}") from error
    results = payload.get("results")
    if not isinstance(results, list):
        raise SearchUnavailable("Search provider response must contain a 'results' array.")
    return {
        "available": True,
        "results": [
            {
                "title": item.get("title"),
                "url": item.get("url"),
                "snippet": item.get("snippet"),
                "source": item.get("source"),
                "date": item.get("date"),
            }
            for item in results[:limit]
            if isinstance(item, dict)
        ],
        "source_type": "configured live external search",
    }
