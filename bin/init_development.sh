#!/bin/zsh

ROOT_FOLDER='/Users/jorgo/FamLedger'

bin/console d:da:drop --force
bin/console d:da:cr
bin/console d:m:mi --no-interaction
bin/console hautelook:fixtures:load --no-interaction --env=dev
setopt rmstarsilent
rm -rf "${ROOT_FOLDER:?}"/*(N)


# import legacy data
bin/console init:import-statements
bin/console app:invoices sync-all
bin/console d:q:s -- "-- Deactive series 'T' after all invoices have been fetched
                   UPDATE series SET is_active=0 WHERE id=3;

                   -- UPDATE DOCUMENTS FROM INVOICES
                   UPDATE document d
                       JOIN invoice i
                       ON JSON_UNQUOTE(JSON_EXTRACT(d.specs, '$.folio')) = i.number
                           AND JSON_UNQUOTE(JSON_EXTRACT(d.specs, '$.series')) = i.series
                           AND d.tenant_id = i.tenant_id
                   SET d.invoice_id = i.id,
                       d.amount     = i.amount
                   WHERE d.type = 'income'
                     AND d.invoice_id IS NULL;

                   -- ASSOCIATE INVOICES WITH PROPERTIES
                   update invoice set property_id=1 where tenant_id = 1 and description like '%oficina #6%';
                   update invoice set property_id=1 where tenant_id = 1 and invoice.recipient_rfc = 'GUMH851129PV4';
                   update invoice set property_id=2 where tenant_id = 1 and description like '%oficina #8%';
                   update invoice set property_id=2 where tenant_id = 1 and invoice.recipient_rfc = 'COM220907IS5';
                   update invoice set property_id=3 where tenant_id = 1 and description like '%Copan #23%';
                   update invoice set property_id=4 where tenant_id = 1 and description like '%Oyamel #14%';
                   update invoice set property_id=6 where tenant_id = 1 and description like '%%oficina #216%';
                   update invoice set property_id=6 where tenant_id = 1 and invoice.recipient_rfc = 'BUDA620727T38';
                   update invoice set property_id=7 where tenant_id = 1 and description like '%%Depto E%';
                   update invoice set property_id=4 where tenant_id = 1 and amount=950000 and property_id is null;

                   -- SQL Query: ASSOCIATE SUBSTITUTED INVOICES
                   UPDATE invoice AS i1 JOIN (SELECT id FROM invoice WHERE live_mode = 1 AND number = 803 AND series = 'A' LIMIT 1) AS i2 ON 1=1 SET i1.substitutes_invoice_id = i2.id WHERE i1.live_mode = 1 AND i1.number = 1 AND i1.series = 'B';
                   UPDATE invoice AS i1 JOIN (SELECT id FROM invoice WHERE live_mode = 1 AND number = 808 AND series = 'A' LIMIT 1) AS i2 ON 1=1 SET i1.substitutes_invoice_id = i2.id WHERE i1.live_mode = 1 AND i1.number = 2 AND i1.series = 'B';
                   UPDATE invoice AS i1 JOIN (SELECT id FROM invoice WHERE live_mode = 1 AND number = 812 AND series = 'A' LIMIT 1) AS i2 ON 1=1 SET i1.substitutes_invoice_id = i2.id WHERE i1.live_mode = 1 AND i1.number = 3 AND i1.series = 'B';
                   UPDATE invoice AS i1 JOIN (SELECT id FROM invoice WHERE live_mode = 1 AND number = 817 AND series = 'A' LIMIT 1) AS i2 ON 1=1 SET i1.substitutes_invoice_id = i2.id WHERE i1.live_mode = 1 AND i1.number = 4 AND i1.series = 'B';
                   UPDATE invoice AS i1 JOIN (SELECT id FROM invoice WHERE live_mode = 1 AND number =   2 AND series = 'T' LIMIT 1) AS i2 ON 1=1 SET i1.substitutes_invoice_id = i2.id WHERE i1.live_mode = 1 AND i1.number = 5 AND i1.series = 'B';

                   UPDATE transaction t
                   INNER JOIN (
                       SELECT
                           d.transaction_id,
                           SUM(d.amount) AS total_document_amount
                       FROM document d
                       WHERE d.type IN ('income', 'expense', 'tax')
                       GROUP BY d.transaction_id
                   ) doc_sum ON t.id = doc_sum.transaction_id
                   SET t.status = CASE
                       WHEN t.amount = doc_sum.total_document_amount THEN 'consolidated'
                       ELSE 'amount-mismatch'
                   END,
                   t.is_consolidated = CASE
                       WHEN t.amount = doc_sum.total_document_amount THEN 1
                       ELSE 0
                   END;

                   UPDATE invoice i
                   INNER JOIN document d on i.id = d.invoice_id
                   INNER JOIN transaction t on d.transaction_id = t.id
                   SET i.payment_date = t.booking_date;"
