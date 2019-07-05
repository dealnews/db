CREATE TABLE IF NOT EXISTS time_dimension (
    time_key character varying(6) NOT NULL,
    hour24_display character varying(2),
    hour12_display character varying(2),
    minute_display character varying(2),
    second_display character varying(2),
    time_range character varying(20),
    period character varying(2)
);