<?php

/*
 * Статистики от SAMO-Tour. Данните се теглят по cron (samo:sync) в нашата база; страниците четат само от нея.
 */
return [
    // тегленето всеки час; включва се, когато връзката към SAMO е настроена
    'sync' => (bool) env('SAMO_SYNC', false),
    // колко дни назад се гледат създадените/променените заявки при всяко теглене
    'sync_days' => (int) env('SAMO_SYNC_DAYS', 14),

    // нашите партньори в SAMO (dbo.partner.inc); всичко останало е външна агенция
    'own_partners' => [1, 13, 20, 34, 480, 511],
    // заявките от сайта
    'online_partners' => [177],
    // dbo.claim.author = 2 — пусната през b2b
    'internet_author' => 2,

    // валутите (dbo.currency.alias): евро — както е; лева — по фиксирания курс; другите не се броят
    'bgn_rate' => '1.95583',

    // колоните на dbo.[order], ако са с други имена; липсваща колона — празно
    'order_columns' => [
        'supplier' => env('SAMO_ORDER_SUPPLIER', 'partner'), // доставчикът на услугата (dbo.partner)
        'hotel' => env('SAMO_ORDER_HOTEL', 'hotel'),
        'town' => env('SAMO_ORDER_TOWN', 'town'),
        'country' => env('SAMO_ORDER_COUNTRY', 'state'),
        'date_from' => env('SAMO_ORDER_DATE_FROM', 'datebeg'),
        'date_to' => env('SAMO_ORDER_DATE_TO', 'dateend'),
        'status' => env('SAMO_ORDER_STATUS', 'status'),
    ],
    // колоната за държава в dbo.partner (за да се познаят турските фирми)
    'partner_country_column' => env('SAMO_PARTNER_COUNTRY', 'state'),
    // как се казва Турция в dbo.state (част от името, без значение на малки/големи букви)
    'turkey_names' => ['турция', 'turkey', 'türkiye', 'turkiye'],
];
