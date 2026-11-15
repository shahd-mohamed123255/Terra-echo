from __future__ import annotations
import json
import logging
import time
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

from terra_echo_ai.config import LLM_API_KEY, LLM_BASE_URL, LLM_MODEL, LLM_TIMEOUT_SECONDS

logger = logging.getLogger(__name__)

class LLMUnavailable(RuntimeError):
    pass


def configured() -> bool:
    return bool(LLM_API_KEY)


def generate_reply(
    question: str,
    farm_context: dict[str, Any],
    tool_results: dict[str, Any],
    history: list[dict[str, str]],
) -> str:
    if not LLM_API_KEY:
        raise LLMUnavailable("No LLM API key is configured.")
    system_message = (
        "You are Abu Qerdan, an agricultural decision-support assistant. "
        "Answer in the language and dialect used by the farmer (English, Arabic, or Egyptian Arabic). "
        "Use only the supplied farm context, tool results, and conversation history for farm-specific claims. "
        "Never invent observations, calculations, citations, or model predictions. "
        "Mark estimates and hypothetical scenarios explicitly; disclose missing inputs and uncertainty. "
        "If evidence is insufficient, say so and ask a focused follow-up question. "
        "Do not give pesticide or fertilizer dosages. You support, not replace, qualified local agronomists."
    )
    messages = [{"role": "system", "content": system_message}]
    messages.extend(history[-8:])
    messages.append(
        {
            "role": "user",
            "content": json.dumps(
                {
                    "question": question,
                    "farm_context": farm_context,
                    "tool_results": tool_results,
                },
                ensure_ascii=False,
                default=str,
            ),
        }
    )
    
    body = json.dumps(
        {"model": LLM_MODEL, "messages": messages, "temperature": 0.2},
        ensure_ascii=False,
    ).encode("utf-8")
    request = Request(
        f"{LLM_BASE_URL}/chat/completions",
        data=body,
        headers={
            "Authorization": f"Bearer {LLM_API_KEY}",
            "Content-Type": "application/json",
        },
        method="POST",
    )
    for attempt in range(2):
        try:
            with urlopen(request, timeout=LLM_TIMEOUT_SECONDS) as response:
                payload = json.loads(response.read().decode("utf-8"))
            break
        except HTTPError as error:
            if attempt == 0 and error.code in {429, 500, 502, 503, 504}:
                time.sleep(0.25)
                continue
            raise LLMUnavailable(
                f"Configured LLM provider request failed: {error}"
            ) from error
        except (URLError, TimeoutError, json.JSONDecodeError) as error:
            if attempt == 0:
                time.sleep(0.25)
                continue
            raise LLMUnavailable(
                f"Configured LLM provider request failed: {error}"
            ) from error
    try:
        reply = payload["choices"][0]["message"]["content"]
    except (KeyError, IndexError, TypeError) as error:
        raise LLMUnavailable("Configured LLM provider returned an unexpected response schema.") from error
    if not isinstance(reply, str) or not reply.strip():
        raise LLMUnavailable("Configured LLM provider returned an empty reply.")
    usage = token_usage(payload)
    if usage:
        logger.info("LLM token usage: %s", usage)
    return reply.strip()

def token_usage(payload: dict[str, Any]) -> dict[str, int] | None:
    usage = payload.get("usage")
    if not isinstance(usage, dict):
        return None
    result: dict[str, int] = {}
    for key in ("prompt_tokens", "completion_tokens", "total_tokens"):
        value = usage.get(key)
        if isinstance(value, int):
            result[key] = value
    return result or None
