from __future__ import annotations

import json
import uuid
from typing import Any

from terra_echo_ai.database import database, json_dump


def start_conversation(summary: dict[str, Any] | None = None) -> dict[str, Any]:
    summary = summary or {}
    conversation_id = f"conv_{uuid.uuid4().hex}"
    with database() as connection:
        connection.execute(
            "INSERT INTO conversations (conversation_id, farm_id, summary_json) VALUES (?, ?, ?)",
            (conversation_id, summary.get("farm_id"), json_dump(summary)),
        )
    return {
        "conversation_id": conversation_id,
        "farm_id": summary.get("farm_id"),
        "summary": summary,
        "messages": [],
    }


def get_conversation(conversation_id: str) -> dict[str, Any]:
    with database() as connection:
        conversation = connection.execute(
            "SELECT conversation_id, farm_id, summary_json, created_at, updated_at FROM conversations WHERE conversation_id = ?",
            (conversation_id,),
        ).fetchone()
        if conversation is None:
            raise KeyError(f"Unknown conversation_id: {conversation_id}")
        messages = connection.execute(
            "SELECT role, content, created_at FROM messages WHERE conversation_id = ? ORDER BY message_id",
            (conversation_id,),
        ).fetchall()
    return {
        **dict(conversation),
        "summary": json.loads(conversation["summary_json"]),
        "messages": [dict(message) for message in messages],
    }


def update_farm_context(conversation_id: str, summary: dict[str, Any]) -> dict[str, Any]:
    current = get_conversation(conversation_id)
    old_farm_id = current.get("farm_id")
    new_farm_id = summary.get("farm_id")
    if old_farm_id and new_farm_id and old_farm_id != new_farm_id:
        raise ValueError("A conversation cannot be reassigned to a different farm.")
    farm_id = old_farm_id or new_farm_id
    summary = dict(summary)
    if farm_id:
        summary["farm_id"] = farm_id
    with database() as connection:
        connection.execute(
            """
            UPDATE conversations
            SET farm_id = ?, summary_json = ?, updated_at = CURRENT_TIMESTAMP
            WHERE conversation_id = ?
            """,
            (farm_id, json_dump(summary), conversation_id),
        )
    return get_conversation(conversation_id)


def add_message(conversation_id: str, role: str, content: str) -> None:
    if role not in {"user", "assistant"}:
        raise ValueError("Message role must be 'user' or 'assistant'.")
    with database() as connection:
        exists = connection.execute(
            "SELECT 1 FROM conversations WHERE conversation_id = ?", (conversation_id,)
        ).fetchone()
        if exists is None:
            raise KeyError(f"Unknown conversation_id: {conversation_id}")
        connection.execute(
            "INSERT INTO messages (conversation_id, role, content) VALUES (?, ?, ?)",
            (conversation_id, role, content),
        )
        connection.execute(
            "UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE conversation_id = ?",
            (conversation_id,),
        )


def recent_messages(conversation_id: str, limit: int = 12) -> list[dict[str, str]]:
    with database() as connection:
        rows = connection.execute(
            """
            SELECT role, content FROM messages
            WHERE conversation_id = ?
            ORDER BY message_id DESC LIMIT ?
            """,
            (conversation_id, limit),
        ).fetchall()
    return [dict(row) for row in reversed(rows)]
