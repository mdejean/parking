-- location

update location set order_no = trim(order_no);
alter table location drop column ogc_fid;
alter table location add primary key (order_no);

-- import_sign -> sign

update import_sign set 
    sign_description = sign_description || ',' || sr_mutcd_code,
    sr_mutcd_code = field_8,
    field_8 = field_9,
    field_9 = null
where field_9 is not null;

update import_sign set
    sign_description = sign_description || ',' || sr_mutcd_code,
    sr_mutcd_code = field_8,
    field_8 = null
where field_8 is not null;

drop table if exists sign;

create table sign (
    boro character,
    order_no character varying,
    seq numeric,
    distx numeric,
    arrow character varying,
    description character varying,
    mutcd_code character varying,
    primary key (order_no, seq)
);

insert into sign 
select 
    trim(srp_boro),
    trim(srp_order),
    srp_seq,
    sr_distx,
    trim(sr_arrow),
    trim(sign_description),
    trim(sr_mutcd_code)
from import_sign;

drop table import_sign;

-- street_segment

create index ix_street_segment_lblockface on street_segment (lblockface);
create index ix_street_segment_rblockface on street_segment (rblockface);
create index ix_street_segment_streetcode on street_segment (streetcode);
create index ix_street_segment_nodeidfrom on street_segment (nodeidfrom);
create index ix_street_segment_nodeidto on street_segment (nodeidto);

-- borough

alter table borough alter column borocode set data type character varying(1);
alter table borough add column fipscode character varying(3);

update borough 
set fipscode = 
    case borocode
        when '1' then '061'
        when '2' then '005'
        when '3' then '047'
        when '4' then '081'
        when '5' then '085'
    end;

-- census_tract

create index ix_census_tract_boroct2010 on census_tract (boroct2010);
create index ix_census_tract_b_ct on census_tract (borocode, ct2010);

-- census_block

create index ix_census_block_bctcb2010 on census_block (bctcb2010);
create index ix_census_block_b_ct_cb on census_block (borocode, ct2010, cb2010);

-- parking_regulation

create index ix_parking_regulation_order_no on parking_regulation(order_no, seq);

-- import_garage -> garage

delete from import_garage where "dca license number" = '1461260-DCA' and bbl is null;

drop table if exists garage;

create table garage (
    license_no character varying,
    name character varying,
    address character varying,
    type character varying,
    spaces int,
    bicycle_spaces int,
    bbl character varying,
    primary key (license_no)
);

SELECT AddGeometryColumn ('public','garage','geom',2263,'POINT',2);

insert into garage
select
    "dca license number",
    "business name",
    "address building" || ' ' || "address street name" || ' ' || "address city" || ', ' || "address state" || ' ' || "address zip", 
    "industry",
    cast(nullif(trim(split_part(split_part(detail,',',1), ':', 2)), '') as int) spaces,
    cast(nullif(trim(split_part(split_part(detail,',',2), ':', 2)), '') as int) bicycle_spaces,
    bbl,
    geom
from import_garage;

drop table import_garage;

-- import_vehicle_ownership, import_employment -> census_stat

create table census_stat (
    boroct2010 character varying,
    population int,
    population_error int,
    vehicles int,
    vehicles_error int,
    workers int,
    workers_error int,
    workers_drive_alone int,
    workers_drive_alone_error int,
    primary key (boroct2010)
);
insert into census_stat
select 
    ct.boroct2010,
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
    b.borocode || ivo.tract boroct2010,
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
) vo on vo.boroct2010 = ct.boroct2010
left join (
select
    b.borocode || right(geoid, 6) boroct2010,
    cast(replace(io.est, ',', '') as int) est,
    cast(replace(replace(io.moe, '+/-', ''), ',', '') as int) error 
    from import_employment io
    join borough b on left(right(geoid, 9), 3) = b.fipscode
    where io.geoid like 'C3100%'
    and io.lineno = 1
) workers on workers.boroct2010 = ct.boroct2010
left join (
select
    b.borocode || right(geoid, 6) boroct2010,
    cast(replace(io.est, ',', '') as int) est,
    cast(replace(replace(io.moe, '+/-', ''), ',', '') as int) error 
    from import_employment io
    join borough b on left(right(geoid, 9), 3) = b.fipscode
    where io.geoid like 'C3100%'
    and io.lineno = 2
) workers_drive_alone on workers_drive_alone.boroct2010 = ct.boroct2010;

drop table import_vehicle_ownership;
drop table import_employment;
