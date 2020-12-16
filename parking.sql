DROP TABLE if exists parking;

CREATE TABLE parking
(
  order_no character varying(10),
  start numeric,
  length numeric,
  day numeric,
  period character varying(20),
  type character(2),
  primary key (order_no, day, period, start)
);