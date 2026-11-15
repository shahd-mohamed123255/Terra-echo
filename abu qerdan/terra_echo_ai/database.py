import json
import sqlite3
from contextlib import contextmanager
from pathlib import Path
from typing import Any, Iterator

from terra_echo_ai.config import DATABASE_PATH


def initialize_database(path: Path | None = None) -> None:
    path = path or DATABASE_PATH
    path.parent.mkdir(parents=True, exist_ok=True)
    with sqlite3.connect(path) as connection:
        connection.executescript(
            """
            CREATE TABLE IF NOT EXISTS conversations (
                conversation_id TEXT PRIMARY KEY,
                farm_id TEXT,
                summary_json TEXT NOT NULL DEFAULT '{}',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS messages (
                message_id INTEGER PRIMARY KEY AUTOINCREMENT,
                conversation_id TEXT NOT NULL,
                role TEXT NOT NULL,
                content TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(conversation_id) REFERENCES conversations(conversation_id)
            );
            CREATE INDEX IF NOT EXISTS idx_messages_conversation
                ON messages(conversation_id, message_id);
            CREATE TABLE IF NOT EXISTS datasets (
                dataset_id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                source TEXT,
                file_path TEXT NOT NULL,
                data_type TEXT NOT NULL,
                schema_json TEXT NOT NULL,
                metadata_json TEXT NOT NULL,
                validation_status TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS environment_cache (
                cache_key TEXT PRIMARY KEY,
                source TEXT NOT NULL,
                payload_json TEXT NOT NULL,
                retrieved_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            """
        )


@contextmanager
def database(path: Path | None = None) -> Iterator[sqlite3.Connection]:
    path = path or DATABASE_PATH
    initialize_database(path)
    connection = sqlite3.connect(path)
    connection.row_factory = sqlite3.Row
    try:
        yield connection
        connection.commit()
    except Exception:
        connection.rollback()
        raise
    finally:
        connection.close()


def json_dump(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"))

