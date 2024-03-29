﻿-- location

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

-- delete non-parking signs
delete from sign 
where not (
    mutcd_code in ('PL', 'BL', 'CL') 
    or mutcd_code like 'PS-%' 
    or mutcd_code like 'SP-%' 
    or mutcd_code like 'R7-%'
);

-- delete locations that have no signs
delete from location
where order_no not in (select order_no from sign group by order_no); 

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

-- import_garage -> dca_garage

delete from import_garage where "dca license number" = '1461260-DCA' and bbl is null;
update import_garage set bbl = null where bbl like '%000000000';
update import_garage set geom = null where ST_X(geom) < -75;

drop table if exists dca_garage;

create table dca_garage (
    license_no character varying,
    name character varying,
    address character varying,
    type character varying,
    spaces int,
    bicycle_spaces int,
    bbl character varying,
    primary key (license_no)
);

SELECT AddGeometryColumn ('public','dca_garage','geom',2263,'POINT',2);

insert into dca_garage
select
    "dca license number",
    "business name",
    "address building" || ' ' || "address street name" || ' ' || "address city" || ', ' || "address state" || ' ' || "address zip", 
    "industry",
    cast(nullif(trim(split_part(split_part(detail,',',1), ':', 2)), '') as int) spaces,
    cast(nullif(trim(split_part(split_part(detail,',',2), ':', 2)), '') as int) bicycle_spaces,
    bbl,
    ST_Transform(ST_SetSRID(geom, 4326), 2263)
from import_garage;

drop table import_garage;

-- pluto

alter table pluto alter column bbl set data type character varying using cast(round(bbl) as character varying);

create index ix_pluto_bbl on pluto (bbl);
