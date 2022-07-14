-- import_vehicle_ownership, import_employment -> census_stat
drop table if exists census_stat;
create table census_stat (
    boroct2020 character varying,
    population int,
    population_error int,
    vehicles int,
    vehicles_error int,
    workers int,
    workers_error int,
    workers_drive_alone int,
    workers_drive_alone_error int,
    primary key (boroct2020)
);
insert into census_stat
select 
    ct.boroct2020,
    vo.population,
    vo.population_error,
    vo.vehicles,
    vo.vehicles_error,
    workers.est workers,
    workers.error workers_error,
    workers_drive_alone.est workers_drive_alone,
    workers_drive_alone.error workers_drive_alone_error
from census_tract ct
left join (
select
    b.borocode || ivo.tract boroct2020,
    population,
    population_error,
    case 
        when vehicles >= 0 then vehicles
        else vehicles_1 + vehicles_2 * 2
    end vehicles,
    case
        when vehicles >= 0 then vehicles_error
        else vehicles_1_error + vehicles_2_error * 2
    end vehicles_error
    from import_vehicle_ownership ivo
    join borough b on ivo.county = b.fipscode
) vo on vo.boroct2020 = ct.boroct2020
left join (
select
    b.borocode || right(geoid, 6) boroct2020,
    cast(replace(io.est, ',', '') as int) est,
    cast(replace(replace(io.moe, '+/-', ''), ',', '') as int) error 
    from import_employment io
    join borough b on left(right(geoid, 9), 3) = b.fipscode
    where io.geoid like 'C3100%'
    and io.lineno = 1
) workers on workers.boroct2020 = ct.boroct2020
left join (
select
    b.borocode || right(geoid, 6) boroct2020,
    cast(replace(io.est, ',', '') as int) est,
    cast(replace(replace(io.moe, '+/-', ''), ',', '') as int) error 
    from import_employment io
    join borough b on left(right(geoid, 9), 3) = b.fipscode
    where io.geoid like 'C3100%'
    and io.lineno = 2
) workers_drive_alone on workers_drive_alone.boroct2020 = ct.boroct2020;

drop table import_vehicle_ownership;
drop table import_employment;
