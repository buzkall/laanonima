<?php

return [

    'title' => 'La Cupida',
    'intro' => 'Three questions and we will tell you what to read. Say yes to what appeals and no to what does not, and we will hand you a book that is in the shop right now.',
    'nav'   => 'La Cupida',

    'start' => [
        'kicker'   => 'Bookshop · Madrid',
        'greeting' => 'Hi, :name',
        'guest'    => 'Anónima',
        'lead'     => 'I am La Cupida of books.',
        'promise'  => 'I am going to ask you 3 questions and hand you :match.',
        'matches'  => ['your next date', 'your next flechazo', 'your next crush', 'your next match'],
        'button'   => 'Start',
    ],

    'questions' => [
        'theme'  => 'What do you feel like reading?',
        'author' => 'And who with?',
        'mood'   => 'What do you want from the book?',
    ],

    'kinds' => [
        'theme'  => 'Genre',
        'author' => 'Author',
        'mood'   => 'Mood',
    ],

    'progress' => 'Question :current of :total',

    /* The same counter with no room for words: it rides in the question's own
     row below `wide:`, where the label would cost the deck a line. */
    'progress_short' => ':current/:total',

    'cards' => [
        'stocked' => '{0} nothing on the table|{1} :count book in the shop|[2,*] :count books in the shop',
    ],

    'moods' => [
        'heartbreak'  => 'Break my heart',
        'laugh'       => 'Make me laugh on the bus',
        'think'       => 'Make me think',
        'escape'      => 'Take me a long way away',
        'short'       => 'Something short, I am pressed for time',
        'doorstop'    => 'Something long, to last me weeks',
        'rage'        => 'Make me angry',
        'comfort'     => 'Something comforting',
        'true_story'  => 'Something that really happened',
        'strange'     => 'Something strange, surprise me',
        'love'        => 'Make me fall in love',
        'mystery'     => 'Keep me on edge',
        'fear'        => 'Give me a bit of a fright',
        'future'      => 'Imagine another world',
        'past'        => 'Take me to another time',
        'learn'       => 'Teach me something',
        'poetry'      => 'Something that sounds beautiful',
        'women'       => 'About women',
        'queer'       => 'LGBTQ stories',
        'family'      => 'About families',
        'city'        => 'Set in a city',
        'nature'      => 'Take me out to the countryside',
        'slow'        => 'Something quiet, no rush',
        'intense'     => 'Something I cannot put down',
        'hope'        => 'Leave me hopeful',
        'dark'        => 'Something very dark',
        'art'         => 'About art or music',
        'politics'    => 'Something political',
        'body'        => 'About the body',
        'mind'        => 'About the mind',
        'food'        => 'Make me hungry',
        'work'        => 'About work',
        'identity'    => 'About where we come from',
        'latin'       => 'Voices from Latin America',
        'world'       => 'From the other side of the world',
        'illustrated' => 'With pictures',
        'classic'     => 'A proper classic',
    ],

    'swipe' => [
        'like' => 'Yes',
        'pass' => 'No',
        'help' => 'Drag the card, or use the buttons and the arrow keys.',
    ],

    'thinking' => [
        'heading' => 'Finding your book…',
        'line'    => 'Looking along the shelf for what fits what you told us.',
    ],

    'result' => [
        'kicker'         => 'Your date',
        'heading'        => 'We think it is this one',
        'by'             => 'by :author',
        'read_more'      => 'See the book',
        'buy'            => 'See it in the shop',
        'at_the_shop'    => 'See it on the bookshop site',
        'more'           => 'Read the rest',
        'synopsis'       => 'Synopsis',
        'again'          => 'Again',
        'fallback_pitch' => 'This one is a hunch, which is how it works some of the time. Open it at any page and you will see.',
    ],

    'empty' => [
        'heading' => 'La Cupida is closed for a moment',
        'line'    => 'The cards are not ready yet. Come back in a little while.',
    ],

    'credit' => [
        'low' => [
            'subject'     => 'La Cupida is running low on credit',
            'body'        => 'By our count there is :remaining left on the Anthropic account.',
            'consequence' => 'When it runs out the page stays up and keeps handing over books, but it stops writing the recommendation: readers get the stock line instead.',
            'action'      => 'Open La Cupida',
        ],

        'exhausted' => [
            'subject'     => 'La Cupida has run out of credit',
            'body'        => 'Anthropic refused the last request for want of credit.',
            'consequence' => 'The page is still up and still handing over books, chosen by our own scoring, but with the stock line instead of a written one.',
            'action'      => 'Open La Cupida',
        ],
    ],

    'admin' => [
        'resource' => [
            'title'            => 'La Cupida',
            'label'            => 'Recommendation',
            'plural_label'     => 'Recommendations',
            'navigation_label' => 'La Cupida',
            'navigation_group' => 'Catalog',
        ],

        'anonymous' => 'No account',

        'fields' => [
            'created_at' => 'When',
            'book'       => 'Book recommended',
            'author'     => 'Author',
            'cover'      => 'Cover',
            'likes'      => 'Said yes to',
            'passes'     => 'Passed on',
            'pitch'      => 'What we told them',
            'written'    => 'Written',
            'user'       => 'Reader',
            'in_catalog' => 'In our catalog',
            'ean'        => 'EAN',
            'cost'       => 'Cost',
            'cost_total' => 'Total',
            'shortlist'  => 'Without the model',
        ],

        'tokens' => ':input in · :output out',

        'cost_none'    => 'Cost nothing',
        'cost_unknown' => 'No rate on file',

        'written' => [
            'yes' => 'AI',
            'no'  => 'Old school code',
        ],

        'filters' => [
            'written'    => 'Written by the model',
            'in_catalog' => 'In our catalog',
            'agreed'     => 'Agreed with the list',
            'sort'       => 'Sort by',
        ],

        'shortlist' => [
            'same'    => 'Without the model we would have given this one',
            'instead' => 'Without the model we would have given: :book',
        ],

        'export' => [
            'action'  => 'Download the log',
            'tooltip' => 'A JSON of whatever is on screen: the answers, the book, the pitch we wrote and what it cost. Meant to be handed to a model to say how well it is doing.',
        ],

        'credit' => [
            'action'       => 'AI balance',
            'heading'      => 'Account balance',
            'description'  => 'Anthropic publishes no balance, so we keep it here: write down what you topped up and we take off what each recommendation costs.',
            'balance'      => 'Amount topped up',
            'balance_hint' => 'In dollars, which is how Anthropic bills.',
            'topped_up_at' => 'Topped up on',
            'remaining'    => 'Left to spend',
            'spent'        => 'Spent since the top-up',
            'unknown'      => 'No balance written down',
            'save'         => 'Save',
            'saved'        => 'Written down. We will warn you when it drops below :threshold.',
        ],

        'sort' => [
            'newest'     => 'Newest first',
            'oldest'     => 'Oldest first',
            'title_asc'  => 'Title (A-Z)',
            'title_desc' => 'Title (Z-A)',
            'cost_desc'  => 'Cost (highest first)',
            'rank_desc'  => 'Furthest from the list',
            'cost_asc'   => 'Cost (lowest first)',
        ],

        'prompt' => [
            'action'       => 'AI prompt',
            'heading'      => 'Instructions for La Cupida',
            'description'  => 'Whatever you add here is appended to the standing instructions. Use it to say who La Cupida is, and for the season, the new-arrivals table, or whoever you are pushing this month.',
            'base_heading' => 'Standing instructions',
            'base_hint'    => 'Not editable here: this is what stops it inventing books. It changes in the code.',
            'extra'        => 'Extra instructions',
            'extra_hint'   => 'In Spanish: who La Cupida is and what the shop wants her to recommend. It describes the bookseller, not the reader: her tastes tilt the choice, but they never count as the answers of whoever is playing. Leave it empty to add nothing.',
            'save'         => 'Save',
            'saved'        => 'Saved. The next recommendation takes it into account.',
        ],
    ],

    'portraits' => [
        'credit'         => 'Photo: :artist · :license · cropped',
        'unknown_artist' => 'photographer unknown',
    ],

];
