begin transaction;
create temporary table supersedes_temp (mutcd_code varchar(92), by varchar(92)) on commit drop;  

insert into supersedes_temp (mutcd_code, by) 
select 
	mutcd_code, 
	unnest(regexp_matches(upper(description), 'SUPERSEDED BY ([\w-]*)', 'g')) 
from (
	select 
		mutcd_code, 
		max(description) description 
	from sign 
	group by mutcd_code
) s;

--'REPLACED BY'
insert into supersedes_temp (mutcd_code, by)
values (
	('R7-20R', 'SP-10B'),
	('R7-20RA', 'SP-10BA')
);

insert into supersedes_temp (mutcd_code, by)
select 
	unnest(regexp_matches(upper(description), 'SUPERSEDES ([\w-]*)', 'g')), 
	s.mutcd_code 
from (
	select 
		mutcd_code, 
		max(description) description 
	from sign 
	group by mutcd_code
) s left join supersedes_temp s2 on s.mutcd_code = s2.by 
where 
	s2.mutcd_code is null;

--cycles
delete from supersedes_temp where mutcd_code = 'SP-175D' and by = 'R7-101'; 
delete from supersedes_temp where mutcd_code = 'SP-1119B' and by = 'PS-7';

delete from supersedes_temp where mutcd_code = by;

delete from supersedes_temp s using supersedes_temp s2 where s.mutcd_code = s2.mutcd_code and s.by > s2.by;


--flatten
drop table if exists supersedes;
create table supersedes (mutcd_code varchar(92), by varchar(92), primary key (mutcd_code));

insert into supersedes (mutcd_code, by)
select 
	s1.mutcd_code, 
	coalesce(s3.by, s2.by, s1.by) "by" 
from supersedes_temp s1 
left join supersedes_temp s2 on s1.by = s2.mutcd_code 
left join supersedes_temp s3 on s2.by = s3.mutcd_code;

--delete supersedes where we don't have the new or old sign
delete from supersedes su 
where su.by not in (select mutcd_code from sign group by mutcd_code) 
or su.mutcd_code not in (select mutcd_code from sign group by mutcd_code);

commit transaction;

--replace all signs with the ones that supersede them
--with sign_types as (
--    select 
--        mutcd_code, 
--        max(description) description 
--    from sign 
--    group by mutcd_code
--) update sign s set mutcd_code = s2.mutcd_code, description = s2.description from 
--supersedes su join sign_types s2 on su.by = s2.mutcd_code 
--where s.mutcd_code = su.mutcd_code;