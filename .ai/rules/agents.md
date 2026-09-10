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

## The pitch says why this one; the plot is the synopsis's job
The reason for the choice is folded into `pitch` rather than being a fourth schema field: the panel already carries the match line and the shop's synopsis, and a third block of prose is a third thing to read on a screen measured to fit a phone.

So the pitch does two jobs -- how the book reads, and why she is handing this one to this reader -- with the authority of somebody who has read it ("te lo doy porque..."), and it is told that the synopsis sits under it so it does not spend its three or four sentences retelling the plot. Both halves are in `baseInstructions()` and in the `pitch` description, because a field is written against what is written next to it.

The fence from the match line still applies here: the reason comes out of the book, never out of reciting what the reader pressed. `CupidaRecommendationTest` holds it.

## The pitch is a matchmaker's, and the promised word crosses to the prompt
A 13-session log export (2026-09-10) showed what a prompt that only says "cita a ciegas" gets from Haiku: every pitch read as a review, ten of thirteen opened with the author's name or the title (both printed right above), liked cards were pinned on books that are not that ("realismo mágico" on a thriller, "dibujo europeo" on an American), passes were recited as arguments ("sin que sea oscuro", "no te robará semanas"), one invented illustrator (Miyazaki on Ronia), and the one send-off under the send-off prompt was a fortune cookie ("que encuentres tu propia verdad"). `baseInstructions()` therefore names the register (casamentera, "es tu tipo", never "creo que te gustará"), bars the author/title-first opening, holds claims to the synopsis, bars a pass as a reason, and makes the match line a goodbye at the door, not life advice. `CupidaRecommendationTest` pins each of those phrases -- keep assertions to fragments that do not cross a heredoc line break.

The opening card promises "tu próxima cita/flechazo/crush/match" by seed (`Cupida::promised()`), and that exact phrase is handed to the model as `CupidaAgent::$promise` and written by `RecommendBook::promptFor()` as the first line ("Le prometiste tu próximo flechazo.") -- session-specific facts go in the prompt, the base instructions only say the word exists. `cupida.result.kickers` is a list parallel to `start.matches` picked by the same index, so the heading over the book says the same word; keep the two lists the same length in es and en.

The log only ever holds a handful of sessions under any one prompt version: judge a prompt change on a fresh export, not on rows written under the previous text.
