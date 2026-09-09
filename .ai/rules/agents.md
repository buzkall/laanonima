---
paths:
  - app/Ai/Agents/CupidaAgent.php
---

# Agents

## The language rule lives in the prompt and beside each written field
Haiku 4.5 has been seen opening a pitch with an English translation of the Spanish synopsis it was handed, on an all-Spanish prompt about a Spanish book. In the same answer `match_line` -- whose schema description pins how the line starts -- stayed in Spanish. A field is written against what is written next to it, so `pitch` and `match_line` both open their `description()` with "En español." and the prompt says it once, hard ("solo en español de España", never translate the synopsis). Keep both; the prompt alone was not enough. `CupidaRecommendationTest` holds them.

## The shop's extra_instructions describe the librera, never the reader
`CupidaSettings::extra_instructions` is often a persona ("La Cupida es queer", "es de izquierdas"), not just a seasonal brief. Appended raw it leaks into `match_line`: every recommendation started telling strangers "porque buscas historias queer". `instructions()` therefore announces the block as describing the bookseller and explicitly bars it from counting as one of the reader's answers. Keep the fence with any change to how the block is rendered.

The other half: `RecommendBook` now passes the reader's likes/passes through `CupidaCatalog::answerLabels()` into `CupidaAgent`, and `promptFor()` puts them before the books. Before that the model was asked to write "porque buscas X" about swipes it had never seen -- scoring happens in PHP and only the shortlist crossed -- so it invented X from whatever else was loudest in the prompt. `CupidaRecommendationTest` holds both.
