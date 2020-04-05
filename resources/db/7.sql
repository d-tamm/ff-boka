-- Shift up status to make place for new 'rejected' status
UPDATE booked_items SET status = status+1 WHERE status > 0;

-- Add field to remember confirmation mails
ALTER TABLE bookings ADD confirmationSent BOOLEAN NOT NULL AFTER token;

UPDATE config SET value=7 WHERE name='db-version';