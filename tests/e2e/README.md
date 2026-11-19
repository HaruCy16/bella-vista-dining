# Browser end-to-end flows

`flows.mjs` drives a real browser through guest and staff flows against the
**test** database (never `bella_vista`):
1. Landing picker → booking flow: Family table with pre-order, a 50% deposit and the balance through the sandbox checkout.
2. Standard table on a phone: blocks a pre-order under ₱1,000, books on-site, then cancels.
3. Two guests race for one VIP room; the second sees the 409 message back on the seating step.
4. Staff: role limits (Kitchen Staff see no customers or reports), search and open a booking, check in from the booking, kitchen columns, a staff phone booking that appears in lists, customers and reports, and a refused double-booking.

```bash
# 1. Test admins + a server on the test DB (port 8097)
docker compose exec app sh -c 'DB_NAME=bella_vista_test ADMIN_PASSWORD=e2e-manager-pass php scripts/seed-admin.php manager Manager "Maria Santos"'
docker compose exec app sh -c 'DB_NAME=bella_vista_test ADMIN_PASSWORD=e2e-kitchen-pass php scripts/seed-admin.php kitchen "Kitchen Staff" "Juan Dela Cruz"'
docker compose exec -d -e DB_NAME=bella_vista_test -e PHP_CLI_SERVER_WORKERS=4 app php -S 0.0.0.0:8097 -t /var/www/html

# 2. Run the flows (screenshots go to tests/e2e/out/)
docker run --rm --network bella_vista_dining_web_app_default -v "$PWD/tests/e2e":/e2e -w /e2e \
  -e BASE_URL=http://app:8097 -e OUT_DIR=/e2e/out mcr.microsoft.com/playwright:v1.49.1-noble \
  sh -c "npm i --no-save playwright@1.49.1 >/dev/null && node flows.mjs"

# 3. Stop the test server
docker compose exec app pkill -f "php -S 0.0.0.0:8097"
```
