<?php

return [

    'resource' => [
        'label'            => 'Book',
        'plural_label'     => 'Books',
        'navigation_label' => 'Books',
        'navigation_group' => 'Catalogue',
    ],

    'publisher' => [
        'label'        => 'Publisher',
        'plural_label' => 'Publishers',
        'create'       => 'New publisher',
    ],

    'sections' => [
        'identification' => 'Identification',
        'record'         => 'Record',
        'edition'        => 'Edition',
        'physical'       => 'Physical',
        'content'        => 'Content',
        'commercial'     => 'Sales',
    ],

    'fields' => [
        'isbn13'                 => 'ISBN',
        'isbn10'                 => 'ISBN-10',
        'ean13'                  => 'EAN',
        'slug'                   => 'URL',
        'external_reference'     => 'Internal reference',
        'title'                  => 'Title',
        'subtitle'               => 'Subtitle',
        'original_title'         => 'Original title',
        'contributors'           => 'Contributors',
        'contributor_name'       => 'Name',
        'contributor_role'       => 'Role',
        'authors_line'           => 'Authors',
        'publisher_id'           => 'Publisher',
        'imprint'                => 'Imprint',
        'collection_name'        => 'Series',
        'collection_number'      => 'Number in series',
        'published_on'           => 'Publication date',
        'published_year'         => 'Year',
        'edition_number'         => 'Edition number',
        'edition_statement'      => 'Edition statement',
        'country_of_publication' => 'Country of publication',
        'city_of_publication'    => 'City of publication',
        'legal_deposit'          => 'Legal deposit',
        'binding'                => 'Binding',
        'pages'                  => 'Pages',
        'height_mm'              => 'Height (mm)',
        'width_mm'               => 'Width (mm)',
        'thickness_mm'           => 'Thickness (mm)',
        'weight_grams'           => 'Weight (g)',
        'measures'               => 'Measurements',
        'language'               => 'Language',
        'original_language'      => 'Original language',
        'subjects'               => 'Subjects',
        'subject_scheme'         => 'Scheme',
        'subject_code'           => 'Code',
        'subject_heading'        => 'Subject',
        'synopsis'               => 'Synopsis',
        'back_cover_text'        => 'Back cover text',
        'cover'                  => 'Cover',
        'covers'                 => 'Images',
        'cover_source_url'       => 'Cover source',
        'cover_color'            => 'Cover colour',
        'price_cents'            => 'Price',
        'vat_rate'               => 'VAT (%)',
        'currency'               => 'Currency',
        'stock'                  => 'Stock',
        'availability'           => 'Availability',
        'is_featured'            => 'Featured',
        'is_active'              => 'Visible on the site',
        'metadata_source'        => 'Metadata source',
        'metadata_synced_at'     => 'Metadata synced at',
        'created_at'             => 'Created',
        'updated_at'             => 'Last modified',
    ],

    'hints' => [
        'isbn13'           => 'Type the ISBN and press the magnifier to fill the record in automatically.',
        'price_cents'      => 'Retail price, VAT included.',
        'slug'             => 'Generated from the title if left blank.',
        'covers'           => 'The first image is the cover. Drag to reorder them.',
        'cover_source_url' => 'Address of the image. Only these sources are accepted: :hosts',
        'cover_color'      => 'Read from the cover only while it is empty. Pick one by hand and it is left alone.',
    ],

    'lookup' => [
        'label'           => 'Look up by ISBN',
        'found_title'     => 'Record found',
        'found_body'      => 'We filled in the details for :title. Check them before saving.',
        'not_found_title' => 'ISBN not found',
        'not_found_body'  => 'None of the sources we consulted has this book. Fill the record in by hand.',
        'invalid_title'   => 'Invalid ISBN',
        'invalid_body'    => 'Check the number: the check digit does not add up.',

        'found_without_cover' => 'No cover was found: download one from an address, or upload the image by hand.',
    ],

    'cover_download' => [
        'label'             => 'Download cover',
        'heading'           => 'Download the cover from an address',
        'submit'            => 'Download',
        'done_title'        => 'Cover downloaded',
        'deferred_title'    => 'Address saved',
        'deferred_body'     => 'The cover will be downloaded when the record is saved.',
        'failed_after_save' => 'The source did not serve an image we can use. Press "Download cover" to try another address, or upload the image by hand.',
        'failed_title'      => 'The image could not be downloaded',
        'failed_body'       => 'Check that the address points at an image from an accepted source and that it is at least :width × :height pixels.',
    ],

    'cover_color' => [
        'reset'   => 'Read it from the cover again',
        'invalid' => 'Enter a colour in #rrggbb format.',
    ],

    'filters' => [
        'featured' => 'Featured',
        'active'   => 'Visible on the site',
    ],

    'binding' => [
        'rustica'    => 'Paperback',
        'tapa_dura'  => 'Hardback',
        'bolsillo'   => 'Pocket',
        'carton'     => 'Board book',
        'espiral'    => 'Spiral bound',
        'ebook'      => 'Ebook',
        'audiolibro' => 'Audiobook',
    ],

    'availability' => [
        'disponible'    => 'In stock',
        'bajo_pedido'   => 'To order',
        'agotado'       => 'Out of stock',
        'descatalogado' => 'Out of print',
        'no_publicado'  => 'Not yet published',
    ],

    'language' => [
        'spa' => 'Spanish',
        'cat' => 'Catalan',
        'eus' => 'Basque',
        'glg' => 'Galician',
        'eng' => 'English',
        'fra' => 'French',
        'por' => 'Portuguese',
        'ita' => 'Italian',
        'deu' => 'German',
    ],

    'contributor_role' => [
        'autor'            => 'Author',
        'traductor'        => 'Translator',
        'ilustrador'       => 'Illustrator',
        'editor_literario' => 'Editor',
        'prologuista'      => 'Foreword',
        'fotografo'        => 'Photographer',
    ],

    'actions' => [
        'view_on_site' => 'See it on the site',
    ],

    'public' => [
        'tagline'           => 'Bookshop · Madrid',
        'buy'               => 'Buy',
        'in_stock_note'     => 'on the new releases table',
        'out_of_stock_note' => 'out of stock in the shop',
        'no_cover'          => 'No cover',
        'synopsis_kicker'   => 'What it is about',
        'object_kicker'     => 'The object',
        'published_format'  => 'MMMM YYYY',
        'measures'          => ':height × :width cm',
        'authors_kicker'    => 'Who wrote it',
        'also_by_them'      => 'Also by them',
        'also_note'         => 'If they publish something new, we order it on day one.',
        'publisher_kicker'  => 'Published by :publisher',
        'publisher_intro'   => 'If you like this one, from the same imprint we usually stock:',
        'footer_line'       => ':name · Independent bookshop · Madrid',

        'shelf_back' => 'See the whole shelf',

        'author' => [
            'kicker' => 'Books by',
            'intro'  => 'Everything we have by :name in the shop. If something of theirs is missing, ask us and we will order it.',
        ],

        'publisher' => [
            'kicker'  => 'Imprint',
            'intro'   => 'What we have in the shop published by :publisher.',
            'empty'   => 'We have nothing by :publisher on the web right now. Ask us: we can almost always order it.',
            'website' => 'Their website',
            'all'     => 'See everything from :publisher',
        ],

        'home' => [
            'heading'      => 'Everything on the table today',
            'intro'        => 'This is what we have in the shop right now. Whatever is not here, we can usually get hold of in a couple of days.',
            'count'        => '{1} :count book on the shelf|[2,*] :count books on the shelf',
            'featured'     => 'Recommended',
            'in_stock'     => 'On the table',
            'out_of_stock' => 'To order',
            'empty'        => 'We have not put any books on the web yet. Come by the shop and we will show you what we have.',
            'prev'         => 'Previous',
            'next'         => 'Next',
        ],

        'in_stock' => [
            'heading' => 'It is on the new releases table.',
            'body'    => 'Come by and leaf through it, or we will keep it aside with your name on it.',
            'cta'     => 'Keep it for me',
            'subject' => 'Reserve :title',
        ],

        'out_of_stock' => [
            'heading' => 'Not in the shop? Ask us for it.',
            'body'    => 'We order it and let you know when it arrives. It usually takes two days.',
            'cta'     => 'Order it for me',
            'subject' => 'Order: :title',
        ],
    ],

];
