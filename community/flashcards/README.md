# Flashcards Community Area

This directory is the contribution surface for the Flashcards learning tool.

A deck contains a title, an optional description, and a list of question/answer cards.

## Field reference

| Field | Required | Type | Constraints |
| --- | --- | --- | --- |
| `title` | Yes | String | 1-120 characters |
| `description` | No | String | At most 500 characters |
| `cards` | Yes | Array | At least one card |
| `cards[].question` | Yes | String | 1-500 characters |
| `cards[].answer` | Yes | String | 1-1,500 characters |

Decks and cards may not contain fields other than those listed above.

## Valid deck

```json
{
  "title": "Learning management systems",
  "description": "A small synthetic deck for checking LMS terminology.",
  "cards": [
    {
      "question": "What does LMS stand for?",
      "answer": "Learning Management System"
    },
    {
      "question": "What is one purpose of an LMS?",
      "answer": "It helps organize and deliver learning activities."
    }
  ]
}
```

## Invalid deck

The following is valid JSON, but it does not match the deck schema:

```json
{
  "title": "",
  "cards": [],
  "difficulty": "beginner"
}
```

It is invalid because `title` is empty, `cards` must contain at least one card,
and `difficulty` is not an allowed deck field.

Rules:

- Keep questions and answers educational and concise.
- Do not copy paid exam banks or copyrighted question collections.
- Do not include personal or student data.
- Validate JSON before opening a Pull Request.
- Prefer useful 10-20 card decks instead of one-card submissions.

See `schema.json` for the machine-readable format.
