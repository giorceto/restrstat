# Деплой

Машината е тази на tc-sales. На нея вече има nginx, PHP 8.4-FPM, MariaDB и FreeTDS с раздел `[samo]`.

```
браузър → прокси 192.168.0.50 → nginx на VM (сайт tc-sales)
        → location /restrstat/ → 127.0.0.1:8081 (сайт restrstat) → PHP-FPM, пул restrstat → /srv/restrstat
```

Проксито на .50 не се променя, защото вече препраща всичко за `sales.tours-club.com` към VM.

## Първо пускане (като root на 192.168.0.156)

```bash
curl -fsSLO https://raw.githubusercontent.com/giorceto/restrstat/claude/affectionate-fermat-idbcu8/scripts/install-vm.sh
bash install-vm.sh
```

Скриптът прави следното:
- създава потребител `restrstat` и папка `/srv/restrstat` и клонира кода;
- създава база и потребител `restrstat` в MariaDB със случайна парола, която записва само в `.env`;
- взема връзката към SAMO (`SAMO_DB_*`) от `/srv/tc-sales/.env`, понеже потребителят `tcsales_ro` е същият и само чете;
- създава PHP-FPM пул `restrstat` и nginx сайт на `127.0.0.1:8081`;
- добавя `include /etc/nginx/snippets/restrstat.conf;` в `/etc/nginx/sites-available/tc-sales`, като пази копие с
  разширение `.bak-restrstat`; ако `nginx -t` не мине, връща файла както е бил;
- настройва cron за `schedule:run` и нощен архив на базата в `/var/backups/restrstat`.

> Ако `provision-debian.sh` на tc-sales се пусне отново, той презаписва сайта на tc-sales и редът `include` изчезва.
> След това пусни отново `bash install-vm.sh`, който го добавя пак.

После:

```bash
sudo -u restrstat php /srv/restrstat/artisan user:create твоят@имейл "Георги"
```

```bash
sudo -u restrstat php /srv/restrstat/artisan samo:check
```

`samo:check` показва колоните на `dbo.[order]`, които ползваме: доставчик, хотел, град, държава и дати. Ако има ✗,
името на колоната се задава в `.env` (`SAMO_ORDER_SUPPLIER=…` и т.н., виж `.env.example`) и се пуска
`php artisan config:cache`.

```bash
sudo -u restrstat php /srv/restrstat/artisan samo:sync --all
```

Накрая включи тегленето всеки час: в `/srv/restrstat/.env` сложи `SAMO_SYNC=true`, после пусни:

```bash
sudo -u restrstat php /srv/restrstat/artisan config:cache
```

Ако искаш първо да видиш страниците без SAMO, използвай измислени данни:
`sudo -u restrstat php /srv/restrstat/artisan demo load`. Преди истинското теглене ги изчисти с `demo clear`.

## Обновяване

```bash
sudo -u restrstat /srv/restrstat/deploy.sh
```

## Проверки

```bash
curl -sI http://127.0.0.1:8081/up            # 200 — приложението работи
curl -sI -H 'Host: sales.tours-club.com' http://127.0.0.1/restrstat/login   # 200 — през сайта на tc-sales
tail -n 50 /srv/restrstat/storage/logs/laravel-$(date +%F).log
```

Последното теглене от SAMO се вижда долу на страницата „Обзор“.
