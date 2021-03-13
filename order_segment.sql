DROP TABLE IF EXISTS public.order_segment;

CREATE TABLE public.order_segment
(
  order_no character varying(10) NOT NULL,
  blockface character varying(10),
  reversed boolean,
  bctcb2010 character varying(12),
  primary key (order_no)
);

CREATE TABLE public.invalid_order
(
  order_no character varying(10) NOT NULL,
  error character varying(400),
  PRIMARY KEY (order_no)
);

create index ix_order_segment_bctcb2010 on order_segment (bctcb2010);
create index ix_order_segment_blockface on order_segment (blockface);