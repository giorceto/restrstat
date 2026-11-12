<?php

namespace App\Services\Samo;

/**
 * Откъдето се теглят данните от SAMO-Tour. Истинското — SqlSamoSource (само чете); в тестовете — подменено.
 * Никакви лични данни на клиенти: само номера, кой/за кого, дати, суми и брой туристи.
 */
interface SamoSource
{
    /**
     * Заявките с номер > $afterInc (по ред на номера), най-много $limit; ако има $since — само създадените или
     * променените от тогава.
     *
     * @return list<array{inc: int, partner: ?int, author: ?int, created_at: string, date_from: ?string, date_to: ?string,
     *     nights: ?int, status: ?int, cancelled_on: ?string, commission_pct: ?string, changed_at: ?string, pax: ?int}>
     */
    public function claims(int $afterInc, int $limit, ?string $since = null): array;

    /**
     * Услугите на тези заявки — сумите вече в евро (всяка по своята валута).
     *
     * @param  list<int>  $claims
     * @return list<array{inc: int, claim: int, supplier: ?int, country: ?int, town: ?int, hotel: ?int, date_from: ?string,
     *     date_to: ?string, status: ?int, sale: ?string, net: ?string, net_currency: ?string, net_original: ?string, unconverted: bool}>
     */
    public function services(array $claims): array;

    /**
     * @param  list<int>  $incs
     * @return list<array{inc: int, name: ?string, country: ?int}>
     */
    public function partners(array $incs): array;

    /**
     * Държави (country), градове (town) или хотели (hotel); parent — държавата на града, градът на хотела.
     *
     * @param  list<int>  $incs
     * @return list<array{inc: int, name: ?string, parent: ?int}>
     */
    public function places(string $kind, array $incs): array;
}
