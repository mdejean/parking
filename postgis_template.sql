select exists(select 1 from pg_catalog.pg_database 
	where datname='postgis_template') as e \gset
\if :e
	alter database postgis_template with is_template false;
\endif

drop database if exists postgis_template;

create database postgis_template with is_template = true;

\c postgis_template

CREATE EXTENSION IF NOT EXISTS postgis WITH SCHEMA public;

\c postgres

alter database postgis_template with allow_connections false;
