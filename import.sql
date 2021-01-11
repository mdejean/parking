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

-- census_block

create index ix_census_block_bctcb2010 on census_block (bctcb2010);

-- parking_regulation

create index ix_parking_regulation_order_no on parking_regulation(order_no);
