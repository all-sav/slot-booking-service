# Slot Booking Service

## Установка и запуск

```bash
# 1. Клонирование репозитория
git clone git@github.com:all-sav/slot-booking-service.git
cd slot-booking-service

# 2. Установка зависимостей
composer install

# 3. Настройка окружения
cp .env.example .env

# 4. Настройка подключения к БД в .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=slot_booking
DB_USERNAME=root
DB_PASSWORD=

# 5. Настройка Redis (для кеша и блокировок)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# 6. Генерация ключа приложения
php artisan key:generate

# 7. Запуск миграций и сидеров
php artisan migrate
php artisan db:seed

# 8. Запуск сервера (нужен докер)
./vendor/bin/sail up -d
```

## API Endpoints
**1. Получение доступных слотов**

Метод: GET /api/slots/availability

Описание: Возвращает список всех слотов с информацией о вместимости и доступных местах. Результат кешируется на 10 секунд с защитой от cache stampede.

**Пример запроса:**
```
curl -X GET http://localhost:8000/api/slots/availability
```
**Пример ответа:**
```
[
{
"slot_id": 1,
"capacity": 10,
"remaining": 6
},
{
"slot_id": 2,
"capacity": 5,
"remaining": 0
},
{
"slot_id": 3,
"capacity": 8,
"remaining": 8
}
]
```

---

2. Создание холда

Метод: POST /api/slots/{id}/hold

Заголовки:
Idempotency-Key: <UUID> - Ключ идемпотентности

Описание: Создает временный холд на слот. Холд живет 5 минут. Проверяет доступность мест, но НЕ резервирует их. Повторный запрос с тем же ключом возвращает прежний результат.

Пример запроса:
```
curl -X POST http://localhost:8000/api/slots/1/hold -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000"
```
Пример ответа (201 Created):
```
{
"hold_id": 1,
"slot_id": 1,
"status": "held",
"idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
}
```

---

3. Повторный запрос с тем же ключом (идемпотентность)

**Пример запроса:**
```
curl -X POST http://localhost:8000/api/slots/1/hold -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000"
```
**Пример ответа (200 OK):**
```
{
"hold_id": 1,
"slot_id": 1,
"status": "held",
"idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
"created_at": "2024-01-01T12:00:00+00:00",
"expires_at": "2024-01-01T12:05:00+00:00"
}
```
Ответ идентичен первому запросу (идемпотентность)

---

**4. Подтверждение холда**

Метод: POST /api/holds/{id}/confirm

Описание: Подтверждает холд и атомарно уменьшает remaining в слоте на 1 с защитой от оверсела. После успешного подтверждения инвалидирует кеш доступности.

**Пример запроса:**
```
curl -X POST http://localhost:8000/api/holds/1/confirm
```
**Пример ответа (200 OK):**
```
{
"hold_id": 1,
"slot_id": 1,
"status": "confirmed",
"idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
}
```

---

5. Отмена холда

Метод: DELETE /api/holds/{id}

Описание: Отменяет подтвержденный холд, меняет статус на cancelled и возвращает место в слот. После успешной отмены инвалидирует кеш доступности.

**Пример запроса:**
```
curl -X DELETE http://localhost:8000/api/holds/1
```
Пример ответа (200 OK):
```
{
"hold_id": 1,
"slot_id": 1,
"status": "cancelled",
"idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
"created_at": "2024-01-01T12:00:00+00:00",
"cancelled_at": "2024-01-01T12:03:00+00:00"
}
```

---

## Сценарии тестирования

**1. Идемпотентность при создании холда**

#### Первый запрос - создание холда
```
curl -X POST http://localhost:80/api/slots/1/hold -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000"
```
#### Второй запрос с тем же ключом - возвращает тот же холд
```
curl -X POST http://localhost:80/api/slots/1/hold -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000"
```
**2. Конфликт при оверселе (два подтверждения на одно место)**

#### Шаг 1: Создаем два холда
```
curl -X POST http://localhost:80/api/slots/1/hold -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000"
```
#### Ответ: hold_id = 1
```
curl -X POST http://localhost:80/api/slots/1/hold -H "Idempotency-Key: 550e8400-e29b-41d4-a716-446655440001"
```
#### Ответ: hold_id = 2

#### Шаг 2: Подтверждаем первый холд (успешно)
```
curl -X POST http://localhost:80/api/holds/1/confirm
```
#### Ответ: status = "confirmed"

#### Шаг 3: Подтверждаем второй холд (ошибка, мест нет)
```
curl -X POST http://localhost:80/api/holds/2/confirm
```
#### Ответ: 409 Conflict

---

