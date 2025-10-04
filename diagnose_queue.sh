#!/bin/bash

echo "=== Queue Diagnostics ==="
echo ""

echo "1. Supervisor status:"
sudo supervisorctl status | grep laravel-worker

echo ""
echo "2. Redis queue size:"
redis-cli LLEN queues:default

echo ""
echo "3. Failed jobs count:"
cd /var/www/ai_bot/data/www/ai-bot.site/
/opt/php83/bin/php artisan queue:failed | head -10

echo ""
echo "4. Last lines from worker log:"
tail -5 /var/www/ai_bot/data/www/ai-bot.site/storage/logs/worker.log

echo ""
echo "5. Laravel log (queue related):"
grep -E "(SyncConversationToCrm|queue|Queue)" /var/www/ai_bot/data/www/ai-bot.site/storage/logs/laravel.log | tail -5

echo ""
echo "6. Process check:"
ps aux | grep -E "(queue:work|laravel-worker)" | grep -v grep