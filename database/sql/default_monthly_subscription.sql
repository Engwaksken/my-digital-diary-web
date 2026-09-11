/*
|--------------------------------------------------------------------------
| My Digital Diary — Default Monthly Subscription
|--------------------------------------------------------------------------
|
| 1. Verify the Monthly plan.
| 2. Backfill existing TRIAL users who have no explicitly selected plan.
| 3. Optional DB trigger: ensures future users without a selected package
|    receive Monthly even if they are created outside Laravel.
|
| IMPORTANT:
| The UPDATE deliberately does NOT overwrite users whose
| subscription_plan_id is already set.
|
*/

-- ---------------------------------------------------------------------
-- STEP 1: VERIFY MONTHLY PLAN
-- ---------------------------------------------------------------------

SELECT id, name
FROM subscription_plans
WHERE LOWER(TRIM(name)) = 'monthly'
LIMIT 1;


-- ---------------------------------------------------------------------
-- STEP 2: BACKFILL CURRENT TRIAL USERS
-- ---------------------------------------------------------------------
--
-- Assign Monthly only when there is no existing selected package.
-- Trial/trialing status remains unchanged.
--

UPDATE users AS u
JOIN subscription_plans AS p
    ON LOWER(TRIM(p.name)) = 'monthly'
SET
    u.subscription_plan_id = p.id
WHERE
    LOWER(TRIM(COALESCE(u.subscription_status, ''))) IN ('trial', 'trialing')
    AND (
        u.subscription_plan_id IS NULL
        OR u.subscription_plan_id = 0
    );


-- ---------------------------------------------------------------------
-- STEP 3: VERIFY BACKFILL
-- ---------------------------------------------------------------------

SELECT
    u.id,
    u.name,
    u.email,
    u.subscription_status,
    u.subscription_plan_id,
    p.name AS subscription_plan
FROM users AS u
LEFT JOIN subscription_plans AS p
    ON p.id = u.subscription_plan_id
WHERE
    LOWER(TRIM(COALESCE(u.subscription_status, ''))) IN ('trial', 'trialing')
ORDER BY u.id DESC;


-- ---------------------------------------------------------------------
-- OPTIONAL DATABASE-LEVEL SAFETY NET
-- ---------------------------------------------------------------------
--
-- Laravel's DefaultSubscriptionServiceProvider already handles all
-- application-created users.
--
-- Use this trigger ONLY if users can also be inserted directly into MySQL
-- by external scripts/integrations.
--
-- It preserves any package explicitly supplied at INSERT time.
--

DROP TRIGGER IF EXISTS users_default_monthly_subscription_before_insert;

DELIMITER $$

CREATE TRIGGER users_default_monthly_subscription_before_insert
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    DECLARE monthly_plan_id BIGINT UNSIGNED DEFAULT NULL;

    IF NEW.subscription_plan_id IS NULL OR NEW.subscription_plan_id = 0 THEN
        SELECT id
        INTO monthly_plan_id
        FROM subscription_plans
        WHERE LOWER(TRIM(name)) = 'monthly'
        ORDER BY id ASC
        LIMIT 1;

        IF monthly_plan_id IS NOT NULL THEN
            SET NEW.subscription_plan_id = monthly_plan_id;
        END IF;
    END IF;

    IF NEW.subscription_status IS NULL OR TRIM(NEW.subscription_status) = '' THEN
        SET NEW.subscription_status = 'trial';
    END IF;
END$$

DELIMITER ;
