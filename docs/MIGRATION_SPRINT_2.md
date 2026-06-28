# Sprint 2 - Backend PHP MVC

## Pham vi da thuc hien

Sprint 2 tao backend PHP 8.2 doc lap, chay duoc tren Linux Hosting Apache/Nginx khong can Google Apps Script.

Da tao:

- `.htaccess`: rewrite ve `index.php`.
- `index.php`: REST router chinh.
- `api/index.php`: proxy cho hosting cau hinh thu muc API.
- `config/app.php`, `config/database.php`: cau hinh ung dung va MySQL.
- `app/Core/*`: Autoloader, PDO Database, Request, Response, Router, BaseController, BaseModel.
- `app/Models/*`: User, AuditLog, Household, Citizen, Dashboard.
- `app/Controllers/*`: Auth, Dashboard, Household, Person.
- `views/app.php`: trang placeholder den khi Sprint 3 chuyen frontend.

## API da co

- `GET /api/health`
- `POST /api/auth/setup`
- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `GET /api/dashboard/summary`
- `GET /api/households`
- `POST /api/households`
- `GET /api/households/{id}`
- `PUT /api/households/{id}`
- `DELETE /api/households/{id}`
- `POST /api/households/bulk-delete`
- `GET /api/persons`
- `POST /api/persons`
- `GET /api/persons/{id}`
- `PUT /api/persons/{id}`
- `DELETE /api/persons/{id}`
- `POST /api/persons/{id}/restore`
- `POST /api/persons/bulk-delete`

## Bao mat va phan quyen

- Dang nhap bang email/mat khau noi bo.
- Token dang nhap luu bang `user_sessions`.
- Password dung `password_hash`/`password_verify`.
- Phan quyen theo role va bang `permissions`.
- Audit log ghi vao `audit_logs`.

## Cach khoi tao tren hosting

1. Upload source len hosting.
2. Import `database/database.sql`.
3. Sua `config/database.php`.
4. Goi `POST /api/auth/setup` voi JSON:

```json
{
  "email": "admin@example.com",
  "displayName": "Quan tri he thong",
  "password": "matkhauantoan"
}
```

5. Dang nhap bang `POST /api/auth/login`.

## Gioi han Sprint 2

- Frontend day du chua chuyen, se lam o Sprint 3.
- Import/Export Excel chua chuyen, se lam o Sprint 5.
- PDF/In phieu chua chuyen, se lam o Sprint 6.
- Backup/Restore day du chua chuyen, se lam o Sprint 7.

## Dieu kien sang Sprint 3

- Import thanh cong `database/database.sql`.
- `GET /api/health` tra ve ok.
- Tao duoc admin dau tien bang `/api/auth/setup`.
- Dang nhap lay token thanh cong.
- CRUD ho dan va nhan khau qua API hoat dong on dinh.
