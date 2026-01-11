# sub_users

مشروع هيكل تجريبي للربط بين Vue (Frontend) وPHP (Backend) عبر مسار `/api`.
الهدف من هذا الملف: مرجع سريع يشرح طريقة التشغيل والربط وأهم التفاصيل.

## الهيكل
- `backend/`: باك اند PHP بسيط (Front Controller) في `backend/public/index.php`.
- `frontend/`: فرونت اند Vue + Vite.

## طريقة الربط بين الفرونت اند والباك اند
- الفرونت يستخدم `axios` بقاعدة:
  - `frontend/src/api/httpClient.js`:
    - `baseURL = /api` (أو من `VITE_API_BASE_URL`).
- Vite Proxy يحول `/api` إلى السيرفر المحلي:
  - `frontend/vite.config.js`:
    - `'/api': { target: 'http://localhost', changeOrigin: true }`
- الباك اند يزيل بادئة `/api` ويعالِج المسار داخليًا:
  - `backend/public/index.php`:
    - `preg_replace('#^/api#', '', $path)`

## تشغيل الباك اند (PHP)
خياران:
1) Apache (إذا `/api` موصول إلى `backend/public`)
2) PHP built-in (للتجربة السريعة):
```powershell
php -S localhost:8000 -t backend/public backend/public/index.php
```
ثم جرّب:
- `http://localhost:8000/api/health`

## تشغيل الفرونت اند (Vue + Vite)
```powershell
cd frontend
npm install
npm run dev
```
افتح تطبيق الفرونت، وسيجري اختبار الربط تلقائيًا مع:
- `GET /api/health`
- `POST /api/echo`

### ملاحظة Proxy
لو شغّلت الباك اند على منفذ مختلف (مثل 8000) عدّل:
`frontend/vite.config.js`:
```js
'/api': { target: 'http://localhost:8000', changeOrigin: true }
```

## المتغيرات البيئية
### backend/.env
- `APP_NAME=sub_users`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_NAME=sub_users`
- `DB_USER=root`
- `DB_PASS=`

### frontend/.env
- `VITE_API_BASE_URL=/api`

## نقاط API الحالية (أمثلة)
- `GET /api/health` يعيد حالة السيرفر.
- `POST /api/echo` يعيد نفس الـ JSON المُرسل.

## ملاحظات مهمة
- `frontend/vite.config.js` يستخدم `base: '/2/'` لتوافق النشر تحت مسار `/2/`.
- المشروع حاليًا مثال بسيط وجاهز للتوسعة.
