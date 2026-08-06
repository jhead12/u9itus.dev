**DPFAA raw\_events\_rows Schema Cleanup — Round 1** 

**Objective** 

Remove the approved Round 1 columns from: 

public.raw\_events\_rows 

This cleanup must be implemented as a controlled PostgreSQL/Supabase migration. 

Do not delete columns individually through a Python loop. Do not use CASCADE . Any dependent view, function, trigger, constraint, index, or policy must be identified and reviewed before the migration is completed. 

**Step 1 — Create a schema backup** 

The backup command is executed from a terminal with PostgreSQL tools installed. It is not run inside the Supabase SQL Editor. 

pg\_dump 

\--schema-only 

\--no-owner 

\--no-privileges 

\--file="dpfaa\_schema\_before\_round1\_cleanup\_2026-08-05.sql" 

"$DATABASE\_URL" 

The environment variable must contain the Supabase PostgreSQL connection string: 

export DATABASE\_URL="postgresql://postgres.\[PROJECT\_REF\]:\[PASSWORD\]@\[HOST\]:5432/ postgres" 

Alternatively, create or verify a current Supabase database backup before proceeding. The technician should also save the current definition of raw\_events\_rows : 

1  
pg\_dump 

\--schema-only 

\--no-owner 

\--no-privileges 

\--table="public.raw\_events\_rows" 

\--file="raw\_events\_rows\_before\_round1\_cleanup\_2026-08-05.sql" "$DATABASE\_URL" 

**Step 2 — Confirm the target columns** Run this in the Supabase SQL Editor before making changes. 

WITH approved\_columns(column\_name) AS ( 

VALUES 

('event\_modality\_code'), 

('search\_funnel\_stage\_code'), 

('llm\_inferred\_stage\_code'), 

('responsible\_system\_code'), 

('timestamp\_event\_when\_scraped'), 

('timestamp\_to\_database'), 

('agentic\_batch\_run\_timestamp'), 

('source\_platform\_code'), 

('source\_architecture\_code'), 

('environment\_type\_code'), 

('device\_type\_code'), 

('geolocation\_code'), 

('severity\_score\_code'), 

('llm\_drift\_type\_code'), 

('drift\_direction\_code'), 

('llm\_generative\_drift\_score'), 

('llm\_generative\_drift\_score\_code'), 

('decision\_architecture\_type\_code'), 

('ai\_intervention\_archetype\_code'), 

('level\_of\_influence\_code'), 

('llm\_ai\_intervention\_type\_code'), 

('system\_name\_mapping\_code'), 

('llm\_content\_rewrite\_type\_code'), 

('primary\_kpi'), 

('value\_propositions\_list\_code'), 

('final\_destination\_class\_code'), 

('competitor\_name\_code'), 

('cta\_visibility\_state\_code'), 

('ai\_decision\_sequence\_order\_code'), 

2  
('ranking\_position\_code'), 

('agent\_model\_name\_code'), 

('agent\_policy\_reasoning\_code'), 

('product\_line\_code'), 

('product\_gender\_segment\_code'), 

('product\_fit\_category\_code'), 

('algorithm\_platform\_ad\_type\_code'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage\_code'), ('unexpected\_redirect\_detected\_code'), 

('architecture\_collision\_type\_code'), 

('agent\_reasoning\_type\_code'), 

('algorithm\_platform\_ad\_type\_risk\_code'), 

('cta\_type\_code'), 

('num\_interfered\_decisions'), 

('num\_diverted\_decisions'), 

('num\_synthetic\_events'), 

('num\_aligned\_decisions'), 

('num\_misaligned\_decisions'), 

('num\_hijacked\_events'), 

('num\_diluted\_decisions'), 

('observation\_mode\_code'), 

('interference\_event\_code'), 

('interference\_event\_score'), 

('primary\_divergence\_reason\_score'), 

('strategic\_weight\_score'), 

('strategic\_weight\_action'), 

('primary\_divergence\_reason\_code'), 

('currency\_code'), 

('abandonment\_reason\_code'), 

('actual\_roas'), 

('event\_uuid\_type\_code'), 

('event\_uuid\_scope\_code'), 

('interference\_event\_type\_code'), 

('primary\_drift\_reason\_code'), 

('strategic\_objective'), 

('intended\_destination'), 

('allowed\_alternatives'), 

('final\_destination\_class'), 

('algorithm\_platform\_ad\_type'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage'), ('algorithm\_platform\_ad\_type\_risk'), 

('product\_sku\_number'), 

('screenshot\_ref'), 

('dom\_snapshot\_ref'), 

('html\_capture\_ref'), 

('country\_code\_region'), 

('agent\_reasoning\_type'), 

('event\_modality'), 

3  
('cross\_system\_drift\_path'), 

('tester\_id'), 

('drift\_state'), 

('intent\_match\_classification') 

) 

SELECT 

a.column\_name, 

CASE 

WHEN c.column\_name IS NOT NULL THEN 'FOUND' 

ELSE 'NOT FOUND' 

END AS column\_status, 

c.data\_type, 

c.is\_nullable, 

c.column\_default 

FROM approved\_columns a 

LEFT JOIN information\_schema.columns c 

ON c.table\_schema \= 'public' 

AND c.table\_name \= 'raw\_events\_rows' 

AND c.column\_name \= a.column\_name 

ORDER BY a.column\_name; 

The approved list has been deduplicated. Repeated entries from the original working list appear only once. 

**Step 3 — Audit dependent views and materialized views** 

Run before attempting the migration. 

WITH approved\_columns(column\_name) AS ( 

VALUES 

('event\_modality\_code'), 

('search\_funnel\_stage\_code'), 

('llm\_inferred\_stage\_code'), 

('responsible\_system\_code'), 

('timestamp\_event\_when\_scraped'), 

('timestamp\_to\_database'), 

('agentic\_batch\_run\_timestamp'), 

('source\_platform\_code'), 

('source\_architecture\_code'), 

('environment\_type\_code'), 

('device\_type\_code'), 

('geolocation\_code'), 

4  
('severity\_score\_code'), 

('llm\_drift\_type\_code'), 

('drift\_direction\_code'), 

('llm\_generative\_drift\_score'), 

('llm\_generative\_drift\_score\_code'), 

('decision\_architecture\_type\_code'), 

('ai\_intervention\_archetype\_code'), 

('level\_of\_influence\_code'), 

('llm\_ai\_intervention\_type\_code'), 

('system\_name\_mapping\_code'), 

('llm\_content\_rewrite\_type\_code'), 

('primary\_kpi'), 

('value\_propositions\_list\_code'), 

('final\_destination\_class\_code'), 

('competitor\_name\_code'), 

('cta\_visibility\_state\_code'), 

('ai\_decision\_sequence\_order\_code'), 

('ranking\_position\_code'), 

('agent\_model\_name\_code'), 

('agent\_policy\_reasoning\_code'), 

('product\_line\_code'), 

('product\_gender\_segment\_code'), 

('product\_fit\_category\_code'), 

('algorithm\_platform\_ad\_type\_code'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage\_code'), ('unexpected\_redirect\_detected\_code'), 

('architecture\_collision\_type\_code'), 

('agent\_reasoning\_type\_code'), 

('algorithm\_platform\_ad\_type\_risk\_code'), 

('cta\_type\_code'), 

('num\_interfered\_decisions'), 

('num\_diverted\_decisions'), 

('num\_synthetic\_events'), 

('num\_aligned\_decisions'), 

('num\_misaligned\_decisions'), 

('num\_hijacked\_events'), 

('num\_diluted\_decisions'), 

('observation\_mode\_code'), 

('interference\_event\_code'), 

('interference\_event\_score'), 

('primary\_divergence\_reason\_score'), 

('strategic\_weight\_score'), 

('strategic\_weight\_action'), 

('primary\_divergence\_reason\_code'), 

('currency\_code'), 

('abandonment\_reason\_code'), 

('actual\_roas'), 

('event\_uuid\_type\_code'), 

5  
('event\_uuid\_scope\_code'), 

('interference\_event\_type\_code'), 

('primary\_drift\_reason\_code'), 

('strategic\_objective'), 

('intended\_destination'), 

('allowed\_alternatives'), 

('final\_destination\_class'), 

('algorithm\_platform\_ad\_type'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage'), ('algorithm\_platform\_ad\_type\_risk'), 

('product\_sku\_number'), 

('screenshot\_ref'), 

('dom\_snapshot\_ref'), 

('html\_capture\_ref'), 

('country\_code\_region'), 

('agent\_reasoning\_type'), 

('event\_modality'), 

('cross\_system\_drift\_path'), 

('tester\_id'), 

('drift\_state'), 

('intent\_match\_classification') 

) 

SELECT 

'VIEW' AS object\_type, 

v.schemaname AS schema\_name, 

v.viewname AS object\_name, 

a.column\_name AS referenced\_column 

FROM pg\_views v 

JOIN approved\_columns a 

ON v.definition ILIKE '%' || a.column\_name || '%' 

WHERE v.schemaname NOT IN ('pg\_catalog', 'information\_schema') UNION ALL 

SELECT 

'MATERIALIZED VIEW', 

m.schemaname, 

m.matviewname, 

a.column\_name 

FROM pg\_matviews m 

JOIN approved\_columns a 

ON m.definition ILIKE '%' || a.column\_name || '%' 

WHERE m.schemaname NOT IN ('pg\_catalog', 'information\_schema') ORDER BY object\_type, schema\_name, object\_name, referenced\_column; 

6  
Known views that may require revision include: 

public.raw\_events\_enriched 

public.v\_raw\_events\_clean 

public.v\_raw\_events\_visualization\_compat 

The technician must update or recreate affected views without the deleted columns. 

**Step 4 — Audit dependent functions and procedures** 

WITH approved\_columns(column\_name) AS ( 

VALUES 

('event\_modality\_code'), 

('search\_funnel\_stage\_code'), 

('llm\_inferred\_stage\_code'), 

('responsible\_system\_code'), 

('timestamp\_event\_when\_scraped'), 

('timestamp\_to\_database'), 

('agentic\_batch\_run\_timestamp'), 

('source\_platform\_code'), 

('source\_architecture\_code'), 

('environment\_type\_code'), 

('device\_type\_code'), 

('geolocation\_code'), 

('severity\_score\_code'), 

('llm\_drift\_type\_code'), 

('drift\_direction\_code'), 

('llm\_generative\_drift\_score'), 

('llm\_generative\_drift\_score\_code'), 

('decision\_architecture\_type\_code'), 

('ai\_intervention\_archetype\_code'), 

('level\_of\_influence\_code'), 

('llm\_ai\_intervention\_type\_code'), 

('system\_name\_mapping\_code'), 

('llm\_content\_rewrite\_type\_code'), 

('primary\_kpi'), 

('value\_propositions\_list\_code'), 

('final\_destination\_class\_code'), 

('competitor\_name\_code'), 

('cta\_visibility\_state\_code'), 

('ai\_decision\_sequence\_order\_code'), 

7  
('ranking\_position\_code'), 

('agent\_model\_name\_code'), 

('agent\_policy\_reasoning\_code'), 

('product\_line\_code'), 

('product\_gender\_segment\_code'), 

('product\_fit\_category\_code'), 

('algorithm\_platform\_ad\_type\_code'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage\_code'), ('unexpected\_redirect\_detected\_code'), 

('architecture\_collision\_type\_code'), 

('agent\_reasoning\_type\_code'), 

('algorithm\_platform\_ad\_type\_risk\_code'), 

('cta\_type\_code'), 

('num\_interfered\_decisions'), 

('num\_diverted\_decisions'), 

('num\_synthetic\_events'), 

('num\_aligned\_decisions'), 

('num\_misaligned\_decisions'), 

('num\_hijacked\_events'), 

('num\_diluted\_decisions'), 

('observation\_mode\_code'), 

('interference\_event\_code'), 

('interference\_event\_score'), 

('primary\_divergence\_reason\_score'), 

('strategic\_weight\_score'), 

('strategic\_weight\_action'), 

('primary\_divergence\_reason\_code'), 

('currency\_code'), 

('abandonment\_reason\_code'), 

('actual\_roas'), 

('event\_uuid\_type\_code'), 

('event\_uuid\_scope\_code'), 

('interference\_event\_type\_code'), 

('primary\_drift\_reason\_code'), 

('strategic\_objective'), 

('intended\_destination'), 

('allowed\_alternatives'), 

('final\_destination\_class'), 

('algorithm\_platform\_ad\_type'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage'), ('algorithm\_platform\_ad\_type\_risk'), 

('product\_sku\_number'), 

('screenshot\_ref'), 

('dom\_snapshot\_ref'), 

('html\_capture\_ref'), 

('country\_code\_region'), 

('agent\_reasoning\_type'), 

('event\_modality'), 

8  
('cross\_system\_drift\_path'), 

('tester\_id'), 

('drift\_state'), 

('intent\_match\_classification') 

), 

database\_functions AS ( 

SELECT 

n.nspname AS schema\_name, 

p.proname AS object\_name, 

p.prokind, 

pg\_get\_functiondef(p.oid) AS definition 

FROM pg\_proc p 

JOIN pg\_namespace n 

ON n.oid \= p.pronamespace 

WHERE n.nspname NOT IN ('pg\_catalog', 'information\_schema') AND p.prokind IN ('f', 'p') 

) 

SELECT 

CASE 

WHEN f.prokind \= 'p' THEN 'PROCEDURE' 

ELSE 'FUNCTION' 

END AS object\_type, 

f.schema\_name, 

f.object\_name, 

a.column\_name AS referenced\_column 

FROM database\_functions f 

JOIN approved\_columns a 

ON f.definition ILIKE '%' || a.column\_name || '%' 

ORDER BY object\_type, schema\_name, object\_name, referenced\_column; Known trigger functions requiring review may include: 

public.fn\_raw\_events\_sync\_standardized\_column\_a 

public.fn\_raw\_events\_sync\_compat\_fields 

public.fn\_raw\_events\_sync\_query\_fields 

Any function referring to a deleted column must be updated before or within the same migration transaction. 

9  
**Step 5 — Audit triggers** 

SELECT 

n.nspname AS schema\_name, 

c.relname AS table\_name, 

t.tgname AS trigger\_name, 

pg\_get\_triggerdef(t.oid, true) AS trigger\_definition FROM pg\_trigger t 

JOIN pg\_class c 

ON c.oid \= t.tgrelid 

JOIN pg\_namespace n 

ON n.oid \= c.relnamespace 

WHERE n.nspname \= 'public' 

AND c.relname \= 'raw\_events\_rows' 

AND NOT t.tgisinternal 

ORDER BY t.tgname; 

The technician must inspect every trigger function named in the result. 

**Step 6 — Audit indexes** 

WITH approved\_columns(column\_name) AS ( 

VALUES 

('event\_modality\_code'), 

('search\_funnel\_stage\_code'), 

('llm\_inferred\_stage\_code'), 

('responsible\_system\_code'), 

('timestamp\_event\_when\_scraped'), 

('timestamp\_to\_database'), 

('agentic\_batch\_run\_timestamp'), 

('source\_platform\_code'), 

('source\_architecture\_code'), 

('environment\_type\_code'), 

('device\_type\_code'), 

('geolocation\_code'), 

('severity\_score\_code'), 

('llm\_drift\_type\_code'), 

('drift\_direction\_code'), 

('llm\_generative\_drift\_score'), 

('llm\_generative\_drift\_score\_code'), 

('decision\_architecture\_type\_code'), 

('ai\_intervention\_archetype\_code'), 

('level\_of\_influence\_code'), 

10  
('llm\_ai\_intervention\_type\_code'), 

('system\_name\_mapping\_code'), 

('llm\_content\_rewrite\_type\_code'), 

('primary\_kpi'), 

('value\_propositions\_list\_code'), 

('final\_destination\_class\_code'), 

('competitor\_name\_code'), 

('cta\_visibility\_state\_code'), 

('ai\_decision\_sequence\_order\_code'), 

('ranking\_position\_code'), 

('agent\_model\_name\_code'), 

('agent\_policy\_reasoning\_code'), 

('product\_line\_code'), 

('product\_gender\_segment\_code'), 

('product\_fit\_category\_code'), 

('algorithm\_platform\_ad\_type\_code'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage\_code'), ('unexpected\_redirect\_detected\_code'), 

('architecture\_collision\_type\_code'), 

('agent\_reasoning\_type\_code'), 

('algorithm\_platform\_ad\_type\_risk\_code'), 

('cta\_type\_code'), 

('num\_interfered\_decisions'), 

('num\_diverted\_decisions'), 

('num\_synthetic\_events'), 

('num\_aligned\_decisions'), 

('num\_misaligned\_decisions'), 

('num\_hijacked\_events'), 

('num\_diluted\_decisions'), 

('observation\_mode\_code'), 

('interference\_event\_code'), 

('interference\_event\_score'), 

('primary\_divergence\_reason\_score'), 

('strategic\_weight\_score'), 

('strategic\_weight\_action'), 

('primary\_divergence\_reason\_code'), 

('currency\_code'), 

('abandonment\_reason\_code'), 

('actual\_roas'), 

('event\_uuid\_type\_code'), 

('event\_uuid\_scope\_code'), 

('interference\_event\_type\_code'), 

('primary\_drift\_reason\_code'), 

('strategic\_objective'), 

('intended\_destination'), 

('allowed\_alternatives'), 

('final\_destination\_class'), 

('algorithm\_platform\_ad\_type'), 

11  
('algorithm\_platform\_ad\_type\_primary\_funnel\_stage'), 

('algorithm\_platform\_ad\_type\_risk'), 

('product\_sku\_number'), 

('screenshot\_ref'), 

('dom\_snapshot\_ref'), 

('html\_capture\_ref'), 

('country\_code\_region'), 

('agent\_reasoning\_type'), 

('event\_modality'), 

('cross\_system\_drift\_path'), 

('tester\_id'), 

('drift\_state'), 

('intent\_match\_classification') 

) 

SELECT 

i.schemaname AS schema\_name, 

i.tablename AS table\_name, 

i.indexname, 

a.column\_name AS referenced\_column, 

i.indexdef 

FROM pg\_indexes i 

JOIN approved\_columns a 

ON i.indexdef ILIKE '%' || a.column\_name || '%' 

WHERE i.schemaname \= 'public' 

AND i.tablename \= 'raw\_events\_rows' 

ORDER BY i.indexname, a.column\_name; 

Indexes directly tied to deleted columns normally need to be removed or rebuilt without those fields. 

**Step 7 — Audit constraints** 

WITH approved\_columns(column\_name) AS ( 

VALUES 

('event\_modality\_code'), 

('search\_funnel\_stage\_code'), 

('llm\_inferred\_stage\_code'), 

('responsible\_system\_code'), 

('timestamp\_event\_when\_scraped'), 

('timestamp\_to\_database'), 

('agentic\_batch\_run\_timestamp'), 

('source\_platform\_code'), 

('source\_architecture\_code'), 

('environment\_type\_code'), 

12  
('device\_type\_code'), 

('geolocation\_code'), 

('severity\_score\_code'), 

('llm\_drift\_type\_code'), 

('drift\_direction\_code'), 

('llm\_generative\_drift\_score'), 

('llm\_generative\_drift\_score\_code'), 

('decision\_architecture\_type\_code'), 

('ai\_intervention\_archetype\_code'), 

('level\_of\_influence\_code'), 

('llm\_ai\_intervention\_type\_code'), 

('system\_name\_mapping\_code'), 

('llm\_content\_rewrite\_type\_code'), 

('primary\_kpi'), 

('value\_propositions\_list\_code'), 

('final\_destination\_class\_code'), 

('competitor\_name\_code'), 

('cta\_visibility\_state\_code'), 

('ai\_decision\_sequence\_order\_code'), 

('ranking\_position\_code'), 

('agent\_model\_name\_code'), 

('agent\_policy\_reasoning\_code'), 

('product\_line\_code'), 

('product\_gender\_segment\_code'), 

('product\_fit\_category\_code'), 

('algorithm\_platform\_ad\_type\_code'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage\_code'), ('unexpected\_redirect\_detected\_code'), 

('architecture\_collision\_type\_code'), 

('agent\_reasoning\_type\_code'), 

('algorithm\_platform\_ad\_type\_risk\_code'), 

('cta\_type\_code'), 

('num\_interfered\_decisions'), 

('num\_diverted\_decisions'), 

('num\_synthetic\_events'), 

('num\_aligned\_decisions'), 

('num\_misaligned\_decisions'), 

('num\_hijacked\_events'), 

('num\_diluted\_decisions'), 

('observation\_mode\_code'), 

('interference\_event\_code'), 

('interference\_event\_score'), 

('primary\_divergence\_reason\_score'), 

('strategic\_weight\_score'), 

('strategic\_weight\_action'), 

('primary\_divergence\_reason\_code'), 

('currency\_code'), 

('abandonment\_reason\_code'), 

13  
('actual\_roas'), 

('event\_uuid\_type\_code'), 

('event\_uuid\_scope\_code'), 

('interference\_event\_type\_code'), 

('primary\_drift\_reason\_code'), 

('strategic\_objective'), 

('intended\_destination'), 

('allowed\_alternatives'), 

('final\_destination\_class'), 

('algorithm\_platform\_ad\_type'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage'), ('algorithm\_platform\_ad\_type\_risk'), 

('product\_sku\_number'), 

('screenshot\_ref'), 

('dom\_snapshot\_ref'), 

('html\_capture\_ref'), 

('country\_code\_region'), 

('agent\_reasoning\_type'), 

('event\_modality'), 

('cross\_system\_drift\_path'), 

('tester\_id'), 

('drift\_state'), 

('intent\_match\_classification') 

), 

table\_constraints AS ( 

SELECT 

con.conname AS constraint\_name, 

pg\_get\_constraintdef(con.oid, true) AS definition FROM pg\_constraint con 

JOIN pg\_class rel 

ON rel.oid \= con.conrelid 

JOIN pg\_namespace n 

ON n.oid \= rel.relnamespace 

WHERE n.nspname \= 'public' 

AND rel.relname \= 'raw\_events\_rows' 

) 

SELECT 

c.constraint\_name, 

a.column\_name AS referenced\_column, 

c.definition 

FROM table\_constraints c 

JOIN approved\_columns a 

ON c.definition ILIKE '%' || a.column\_name || '%' ORDER BY c.constraint\_name, a.column\_name; 

14  
**Step 8 — Audit row-level security policies** 

WITH approved\_columns(column\_name) AS ( 

VALUES 

('event\_modality\_code'), 

('search\_funnel\_stage\_code'), 

('llm\_inferred\_stage\_code'), 

('responsible\_system\_code'), 

('timestamp\_event\_when\_scraped'), 

('timestamp\_to\_database'), 

('agentic\_batch\_run\_timestamp'), 

('source\_platform\_code'), 

('source\_architecture\_code'), 

('environment\_type\_code'), 

('device\_type\_code'), 

('geolocation\_code'), 

('severity\_score\_code'), 

('llm\_drift\_type\_code'), 

('drift\_direction\_code'), 

('llm\_generative\_drift\_score'), 

('llm\_generative\_drift\_score\_code'), 

('decision\_architecture\_type\_code'), 

('ai\_intervention\_archetype\_code'), 

('level\_of\_influence\_code'), 

('llm\_ai\_intervention\_type\_code'), 

('system\_name\_mapping\_code'), 

('llm\_content\_rewrite\_type\_code'), 

('primary\_kpi'), 

('value\_propositions\_list\_code'), 

('final\_destination\_class\_code'), 

('competitor\_name\_code'), 

('cta\_visibility\_state\_code'), 

('ai\_decision\_sequence\_order\_code'), 

('ranking\_position\_code'), 

('agent\_model\_name\_code'), 

('agent\_policy\_reasoning\_code'), 

('product\_line\_code'), 

('product\_gender\_segment\_code'), 

('product\_fit\_category\_code'), 

('algorithm\_platform\_ad\_type\_code'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage\_code'), ('unexpected\_redirect\_detected\_code'), 

('architecture\_collision\_type\_code'), 

('agent\_reasoning\_type\_code'), 

('algorithm\_platform\_ad\_type\_risk\_code'), 

('cta\_type\_code'), 

15  
('num\_interfered\_decisions'), 

('num\_diverted\_decisions'), 

('num\_synthetic\_events'), 

('num\_aligned\_decisions'), 

('num\_misaligned\_decisions'), 

('num\_hijacked\_events'), 

('num\_diluted\_decisions'), 

('observation\_mode\_code'), 

('interference\_event\_code'), 

('interference\_event\_score'), 

('primary\_divergence\_reason\_score'), 

('strategic\_weight\_score'), 

('strategic\_weight\_action'), 

('primary\_divergence\_reason\_code'), 

('currency\_code'), 

('abandonment\_reason\_code'), 

('actual\_roas'), 

('event\_uuid\_type\_code'), 

('event\_uuid\_scope\_code'), 

('interference\_event\_type\_code'), 

('primary\_drift\_reason\_code'), 

('strategic\_objective'), 

('intended\_destination'), 

('allowed\_alternatives'), 

('final\_destination\_class'), 

('algorithm\_platform\_ad\_type'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage'), ('algorithm\_platform\_ad\_type\_risk'), 

('product\_sku\_number'), 

('screenshot\_ref'), 

('dom\_snapshot\_ref'), 

('html\_capture\_ref'), 

('country\_code\_region'), 

('agent\_reasoning\_type'), 

('event\_modality'), 

('cross\_system\_drift\_path'), 

('tester\_id'), 

('drift\_state'), 

('intent\_match\_classification') 

) 

SELECT 

p.schemaname AS schema\_name, 

p.tablename AS table\_name, 

p.policyname, 

a.column\_name AS referenced\_column, 

p.qual, 

p.with\_check 

16  
FROM pg\_policies p 

JOIN approved\_columns a 

ON COALESCE(p.qual, '') ILIKE '%' || a.column\_name || '%' OR COALESCE(p.with\_check, '') ILIKE '%' || a.column\_name || '%' WHERE p.schemaname \= 'public' 

AND p.tablename \= 'raw\_events\_rows' 

ORDER BY p.policyname, a.column\_name; 

**Step 9 — Prepare dependency revisions** Before running the column-drop migration: 

1\.    
Save the current definitions of all affected views, functions, triggers, policies, indexes, and constraints. 

2\.    
Prepare revised definitions that omit the approved columns. 

3\.    
Do not use DROP ... CASCADE . 

4\.    
Drop and recreate only the specific dependent objects that require revision. 5\.    
Keep all revisions and column removals inside the same transaction when practical. 

Example pattern: 

BEGIN; 

\-- Example only: 

\-- DROP VIEW IF EXISTS public.v\_raw\_events\_visualization\_compat; \-- DROP VIEW IF EXISTS public.v\_raw\_events\_clean; 

\-- DROP VIEW IF EXISTS public.raw\_events\_enriched; 

\-- Replace or remove affected trigger functions here. \-- Recreate affected indexes, constraints, or policies as needed. 

\-- Run the approved ALTER TABLE statement from Step 10\. \-- Recreate revised views here. 

COMMIT; 

If any operation fails before COMMIT , run: 

ROLLBACK; 

17  
**Step 10 — Round 1 column-removal migration** Run only after the dependency audit and required object revisions are complete. 

BEGIN; 

ALTER TABLE public.raw\_events\_rows 

DROP COLUMN IF EXISTS event\_modality\_code, 

DROP COLUMN IF EXISTS search\_funnel\_stage\_code, 

DROP COLUMN IF EXISTS llm\_inferred\_stage\_code, 

DROP COLUMN IF EXISTS responsible\_system\_code, 

DROP COLUMN IF EXISTS timestamp\_event\_when\_scraped, 

DROP COLUMN IF EXISTS timestamp\_to\_database, 

DROP COLUMN IF EXISTS agentic\_batch\_run\_timestamp, 

DROP COLUMN IF EXISTS source\_platform\_code, 

DROP COLUMN IF EXISTS source\_architecture\_code, 

DROP COLUMN IF EXISTS environment\_type\_code, 

DROP COLUMN IF EXISTS device\_type\_code, 

DROP COLUMN IF EXISTS geolocation\_code, 

DROP COLUMN IF EXISTS severity\_score\_code, 

DROP COLUMN IF EXISTS llm\_drift\_type\_code, 

DROP COLUMN IF EXISTS drift\_direction\_code, 

DROP COLUMN IF EXISTS llm\_generative\_drift\_score, 

DROP COLUMN IF EXISTS llm\_generative\_drift\_score\_code, 

DROP COLUMN IF EXISTS decision\_architecture\_type\_code, 

DROP COLUMN IF EXISTS ai\_intervention\_archetype\_code, 

DROP COLUMN IF EXISTS level\_of\_influence\_code, 

DROP COLUMN IF EXISTS llm\_ai\_intervention\_type\_code, 

DROP COLUMN IF EXISTS system\_name\_mapping\_code, 

DROP COLUMN IF EXISTS llm\_content\_rewrite\_type\_code, 

DROP COLUMN IF EXISTS primary\_kpi, 

DROP COLUMN IF EXISTS value\_propositions\_list\_code, 

DROP COLUMN IF EXISTS final\_destination\_class\_code, 

DROP COLUMN IF EXISTS competitor\_name\_code, 

DROP COLUMN IF EXISTS cta\_visibility\_state\_code, 

DROP COLUMN IF EXISTS ai\_decision\_sequence\_order\_code, 

DROP COLUMN IF EXISTS ranking\_position\_code, 

DROP COLUMN IF EXISTS agent\_model\_name\_code, 

DROP COLUMN IF EXISTS agent\_policy\_reasoning\_code, 

DROP COLUMN IF EXISTS product\_line\_code, 

DROP COLUMN IF EXISTS product\_gender\_segment\_code, 

DROP COLUMN IF EXISTS product\_fit\_category\_code, 

DROP COLUMN IF EXISTS algorithm\_platform\_ad\_type\_code, 

DROP COLUMN IF EXISTS algorithm\_platform\_ad\_type\_primary\_funnel\_stage\_code, DROP COLUMN IF EXISTS unexpected\_redirect\_detected\_code, 

DROP COLUMN IF EXISTS architecture\_collision\_type\_code, 

18  
DROP COLUMN IF EXISTS agent\_reasoning\_type\_code, 

DROP COLUMN IF EXISTS algorithm\_platform\_ad\_type\_risk\_code, DROP COLUMN IF EXISTS cta\_type\_code, 

DROP COLUMN IF EXISTS num\_interfered\_decisions, 

DROP COLUMN IF EXISTS num\_diverted\_decisions, 

DROP COLUMN IF EXISTS num\_synthetic\_events, 

DROP COLUMN IF EXISTS num\_aligned\_decisions, 

DROP COLUMN IF EXISTS num\_misaligned\_decisions, 

DROP COLUMN IF EXISTS num\_hijacked\_events, 

DROP COLUMN IF EXISTS num\_diluted\_decisions, 

DROP COLUMN IF EXISTS observation\_mode\_code, 

DROP COLUMN IF EXISTS interference\_event\_code, 

DROP COLUMN IF EXISTS interference\_event\_score, 

DROP COLUMN IF EXISTS primary\_divergence\_reason\_score, 

DROP COLUMN IF EXISTS strategic\_weight\_score, 

DROP COLUMN IF EXISTS strategic\_weight\_action, 

DROP COLUMN IF EXISTS primary\_divergence\_reason\_code, 

DROP COLUMN IF EXISTS currency\_code, 

DROP COLUMN IF EXISTS abandonment\_reason\_code, 

DROP COLUMN IF EXISTS actual\_roas, 

DROP COLUMN IF EXISTS event\_uuid\_type\_code, 

DROP COLUMN IF EXISTS event\_uuid\_scope\_code, 

DROP COLUMN IF EXISTS interference\_event\_type\_code, 

DROP COLUMN IF EXISTS primary\_drift\_reason\_code, 

DROP COLUMN IF EXISTS strategic\_objective, 

DROP COLUMN IF EXISTS intended\_destination, 

DROP COLUMN IF EXISTS allowed\_alternatives, 

DROP COLUMN IF EXISTS final\_destination\_class, 

DROP COLUMN IF EXISTS algorithm\_platform\_ad\_type, 

DROP COLUMN IF EXISTS algorithm\_platform\_ad\_type\_primary\_funnel\_stage, DROP COLUMN IF EXISTS algorithm\_platform\_ad\_type\_risk, 

DROP COLUMN IF EXISTS product\_sku\_number, 

DROP COLUMN IF EXISTS screenshot\_ref, 

DROP COLUMN IF EXISTS dom\_snapshot\_ref, 

DROP COLUMN IF EXISTS html\_capture\_ref, 

DROP COLUMN IF EXISTS country\_code\_region, 

DROP COLUMN IF EXISTS agent\_reasoning\_type, 

DROP COLUMN IF EXISTS event\_modality, 

DROP COLUMN IF EXISTS cross\_system\_drift\_path, 

DROP COLUMN IF EXISTS tester\_id, 

DROP COLUMN IF EXISTS drift\_state, 

DROP COLUMN IF EXISTS intent\_match\_classification; 

COMMIT; 

Do not add CASCADE . 

19  
If PostgreSQL reports a dependency error, stop and revise the named dependent object. Do not force the column removal. 

**Step 11 — Verify that the columns were removed** 

WITH approved\_columns(column\_name) AS ( 

VALUES 

('event\_modality\_code'), 

('search\_funnel\_stage\_code'), 

('llm\_inferred\_stage\_code'), 

('responsible\_system\_code'), 

('timestamp\_event\_when\_scraped'), 

('timestamp\_to\_database'), 

('agentic\_batch\_run\_timestamp'), 

('source\_platform\_code'), 

('source\_architecture\_code'), 

('environment\_type\_code'), 

('device\_type\_code'), 

('geolocation\_code'), 

('severity\_score\_code'), 

('llm\_drift\_type\_code'), 

('drift\_direction\_code'), 

('llm\_generative\_drift\_score'), 

('llm\_generative\_drift\_score\_code'), 

('decision\_architecture\_type\_code'), 

('ai\_intervention\_archetype\_code'), 

('level\_of\_influence\_code'), 

('llm\_ai\_intervention\_type\_code'), 

('system\_name\_mapping\_code'), 

('llm\_content\_rewrite\_type\_code'), 

('primary\_kpi'), 

('value\_propositions\_list\_code'), 

('final\_destination\_class\_code'), 

('competitor\_name\_code'), 

('cta\_visibility\_state\_code'), 

('ai\_decision\_sequence\_order\_code'), 

('ranking\_position\_code'), 

('agent\_model\_name\_code'), 

('agent\_policy\_reasoning\_code'), 

('product\_line\_code'), 

('product\_gender\_segment\_code'), 

('product\_fit\_category\_code'), 

('algorithm\_platform\_ad\_type\_code'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage\_code'), 

('unexpected\_redirect\_detected\_code'), 

20  
('architecture\_collision\_type\_code'), 

('agent\_reasoning\_type\_code'), 

('algorithm\_platform\_ad\_type\_risk\_code'), 

('cta\_type\_code'), 

('num\_interfered\_decisions'), 

('num\_diverted\_decisions'), 

('num\_synthetic\_events'), 

('num\_aligned\_decisions'), 

('num\_misaligned\_decisions'), 

('num\_hijacked\_events'), 

('num\_diluted\_decisions'), 

('observation\_mode\_code'), 

('interference\_event\_code'), 

('interference\_event\_score'), 

('primary\_divergence\_reason\_score'), 

('strategic\_weight\_score'), 

('strategic\_weight\_action'), 

('primary\_divergence\_reason\_code'), 

('currency\_code'), 

('abandonment\_reason\_code'), 

('actual\_roas'), 

('event\_uuid\_type\_code'), 

('event\_uuid\_scope\_code'), 

('interference\_event\_type\_code'), 

('primary\_drift\_reason\_code'), 

('strategic\_objective'), 

('intended\_destination'), 

('allowed\_alternatives'), 

('final\_destination\_class'), 

('algorithm\_platform\_ad\_type'), 

('algorithm\_platform\_ad\_type\_primary\_funnel\_stage'), ('algorithm\_platform\_ad\_type\_risk'), 

('product\_sku\_number'), 

('screenshot\_ref'), 

('dom\_snapshot\_ref'), 

('html\_capture\_ref'), 

('country\_code\_region'), 

('agent\_reasoning\_type'), 

('event\_modality'), 

('cross\_system\_drift\_path'), 

('tester\_id'), 

('drift\_state'), 

('intent\_match\_classification') 

) 

SELECT 

a.column\_name, 

CASE 

21  
WHEN c.column\_name IS NULL THEN 'REMOVED' 

ELSE 'STILL PRESENT' 

END AS migration\_result 

FROM approved\_columns a 

LEFT JOIN information\_schema.columns c 

ON c.table\_schema \= 'public' 

AND c.table\_name \= 'raw\_events\_rows' 

AND c.column\_name \= a.column\_name 

ORDER BY migration\_result DESC, a.column\_name; 

Every result should be: 

REMOVED 

**Step 12 — Confirm that critical evidence fields remain** 

SELECT 

column\_name, 

data\_type, 

is\_nullable 

FROM information\_schema.columns 

WHERE table\_schema \= 'public' 

AND table\_name \= 'raw\_events\_rows' 

AND column\_name IN ( 

'event\_uuid', 

'created\_at', 

'run\_id', 

'journey\_id', 

'source\_channel', 

'platform\_name', 

'llm\_prompt', 

'agent\_output\_raw', 

'raw\_output\_text', 

'llm\_generated\_text', 

'raw\_log\_content', 

'html\_capture\_content', 

'competitor\_name', 

'severity\_score', 

'intent\_match\_score', 

'agent\_policy\_reasoning' 

22  
) 

ORDER BY column\_name; 

Review this result before completing final sign-off. 

**Step 13 — Confirm row-count preservation** The migration removes columns only. It must not delete records. 

Run before and after the migration and record both results: 

SELECT 

COUNT(\*) AS total\_raw\_event\_rows, 

COUNT(DISTINCT event\_uuid) AS distinct\_event\_uuids, 

MIN(created\_at) AS earliest\_record, 

MAX(created\_at) AS latest\_record 

FROM public.raw\_events\_rows; 

The row count should remain unchanged unless new ingestion activity occurs during the migration window. 

**Step 14 — Validate core views and current ingestion** 

After recreating affected objects, run: 

SELECT COUNT(\*) AS raw\_events\_enriched\_rows 

FROM public.raw\_events\_enriched; 

SELECT COUNT(\*) AS clean\_view\_rows 

FROM public.v\_raw\_events\_clean; 

SELECT COUNT(\*) AS visualization\_view\_rows 

FROM public.v\_raw\_events\_visualization\_compat; 

Then insert or process a controlled test observation through the normal ingestion pipeline and verify: 1\. The insert succeeds. 

23  
2\.  3\.    
No trigger references a removed column. 

The retained raw evidence fields populate correctly. 4\.    
The required views return the new observation. 

5\.    
No current application, dashboard, or analysis fails. 

**Required technician completion report** Please provide the following after implementation: 

**1\. Final migration SQL** 

Provide the exact SQL migration that was executed, including: 

•    
dependent objects dropped or replaced; 

•    
revised function definitions; 

•    
revised view definitions; 

•    
the final ALTER TABLE statement; 

•    
recreated indexes, constraints, triggers, or policies. 

**2\. Objects modified** 

List every modified object, including: 

Object type 

Schema 

Object name 

Reason for modification 

**3\. Completion confirmation** 

Confirm: 

Migration completed successfully: YES / NO 

Transaction committed: YES / NO 

Database backup completed: YES / NO 

Row count preserved: YES / NO 

Controlled ingestion test passed: YES / NO 

Core views validated: YES / NO 

24  
**4\. Columns not removed** 

For each column that could not be removed, report: 

Column name 

Dependent object 

Reason removal failed 

Recommended corrective action 

**5\. Backup location** 

Provide the location and filename of: 

•    
the complete schema backup; 

•    
the raw\_events\_rows table schema backup; 

•    
the final migration file committed to the project repository. 

Recommended migration filename: 

20260805\_001\_raw\_events\_rows\_round1\_cleanup.sql 

**Prohibited implementation approach** Do not execute column removals through an uncommitted Python loop such as: 

for column in columns: 

execute(f"ALTER TABLE raw\_events\_rows DROP COLUMN {column}") 

That approach can leave the schema partially modified if an operation fails midway. Use one reviewed PostgreSQL migration inside a transaction. 

25