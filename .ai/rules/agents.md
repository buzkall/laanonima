---
paths:
  - app/Ai/Agents/CupidaAgent.php
---

# Agents

## The language rule lives in the prompt and beside each written field
Haiku 4.5 has been seen opening a pitch with an English translation of the Spanish synopsis it was handed, on an all-Spanish prompt about a Spanish book. In the same answer `match_line` -- whose schema description pins the shape of the line -- stayed in Spanish. A field is written against what is written next to it, so `pitch` and `match_line` both open their `description()` with "En español." and the prompt says it once, hard ("solo en español de España", never translate the synopsis). Keep both; the prompt alone was not enough. `CupidaRecommendationTest` holds them.

Saying "solo en español" twice still did not hold, and the log says why the miss is easy to walk past. Two rows out of thirty-nine, both in `pitch`: one opens with a whole English sentence and then switches to Spanish mid-paragraph, the other is a single English adjective inside a Spanish sentence ("es confessional, rabiosa"), which reads as Spanish until it is looked at. So the rule now names the word as well as the sentence, and `pitch` carries the extra clause because that is where both landed.

What it must not become is a ban on English words. "cruising" in a pitch about queer desire, "thriller", "queer" itself -- those are the words Spanish uses, and the shop's own cards are written with them. What is barred is the English spelling of a word Spanish already has: "confessional" for "confesional". The prompt gives both the counter-example and the exception, and `CupidaRecommendationTest` holds the pair.

## The shop's extra_instructions describe the librera, never the reader
`CupidaSettings::extra_instructions` is often a persona ("La Cupida es queer", "es de izquierdas"), not just a seasonal brief. Appended raw it leaks into `match_line`: every recommendation started telling strangers "porque buscas historias queer". `instructions()` therefore announces the block as describing the bookseller and explicitly bars it from counting as one of the reader's answers. Keep the fence with any change to how the block is rendered.

The other half: `RecommendBook` now passes the reader's likes/passes through `CupidaCatalog::answerLabels()` into `CupidaAgent`, and `promptFor()` puts them before the books. Before that the model was asked to write "porque buscas X" about swipes it had never seen -- scoring happens in PHP and only the shortlist crossed -- so it invented X from whatever else was loudest in the prompt. `CupidaRecommendationTest` holds both.

## The match line is a send-off to a date, never a receipt for the swipes
Handing the model the reader's answers (so it would stop inventing a taste) made it recite them instead: every line came back "porque dijiste que sí a X, a Y y a Z" -- the card labels verbatim, moods included. The moods are written in the reader's own mouth ("que me tenga en vilo"), so quoted back in the second person they were ungrammatical too, and the whole line told the reader what they had just pressed.

`match_line` is now the send-off before the date: eight words or less, wishing them the book, never a list and never opening with "Porque". `baseInstructions()` frames the whole thing as a cita a ciegas, bars reciting the answers, and -- where it needs one of them -- asks for it in the librera's words addressed to the reader ("te va a tener en vilo"), one at most. `RecommendBook::promptFor()` says the same thing where the list actually appears, because that is where the temptation is.

What did NOT change: the answers still cross to the prompt (without them the model invents a taste), and "Nunca le atribuyas un tema, un gusto ni una identidad que no haya elegido" stays -- that is the fence against the shop's persona leaking into the reader's mouth. `CupidaRecommendationTest` holds both halves.
