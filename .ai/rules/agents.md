---
paths:
  - app/Ai/Agents/CupidaAgent.php
---

# Agents

## The language rule lives in the prompt and beside each written field
Haiku 4.5 has been seen opening a pitch with an English translation of the Spanish synopsis it was handed, on an all-Spanish prompt about a Spanish book. In the same answer `match_line` -- whose schema description pins how the line starts -- stayed in Spanish. A field is written against what is written next to it, so `pitch` and `match_line` both open their `description()` with "En español." and the prompt says it once, hard ("solo en español de España", never translate the synopsis). Keep both; the prompt alone was not enough. `CupidaRecommendationTest` holds them.
