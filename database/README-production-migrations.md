# Production-миграции

Для применения миграций используется `migrate_production.php`. Скрипт запускается из корня проекта на production-сервере и подключается к MySQL только через переменные окружения.

## Переменные окружения

Обязательные переменные:

```bash
export APP_ENV=production
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_NAME=olaplex
export DB_USER=olaplex_migrator
export DB_PASS='пароль'
```

Пользователю базы нужны права на создание таблицы учёта миграций и изменение объектов, затрагиваемых миграциями. Не используйте пользователя приложения, если на сервере настроен отдельный пользователь для миграций.

## Рекомендуемый порядок запуска

Перед первым запуском сделайте резервную копию базы данных и убедитесь, что код и SQL-файлы соответствуют одной версии релиза.

Сначала проверить состояние без изменений:

```bash
APP_ENV=production php database/migrate_production.php --status
APP_ENV=production php database/migrate_production.php --dry-run
```

Затем применить ожидающие миграции:

```bash
APP_ENV=production php database/migrate_production.php --apply
```

Скрипт применяет только следующие миграции и только в этом порядке:

1. `migration_product_visibility.sql`
2. `migration_preorder_requests.sql`
3. `migration_preorder_archive.sql`

## Защиты скрипта

- без `--apply` база не изменяется;
- `--apply` разрешён только при `APP_ENV=production`;
- используется MySQL advisory lock, чтобы два запуска не выполнялись одновременно;
- применяется таблица `schema_migrations` с SHA-256 контрольной суммой каждого файла;
- изменение уже применённого SQL-файла останавливает выполнение;
- после ошибки следующая попытка продолжает с первой миграции без записи об успешном применении текущей.

DDL в MySQL может выполнять неявный commit. Поэтому перед `--apply` резервная копия обязательна, а при ошибке нужно проверить состояние базы перед повторным запуском.

## Проверка результата

После применения:

```bash
APP_ENV=production php database/migrate_production.php --status
```

Ожидаемый результат: все три миграции имеют статус `[applied]`. Таблица `schema_migrations` является служебной и не участвует в работе магазина.
