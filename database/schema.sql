--
-- PostgreSQL database dump
--

\restrict Cf1xdKThLUC4UypBhQxX9u29ByNgatO6gRTPcqpnZA5cflyUT8Hja9yo6mqTMej

-- Dumped from database version 18.1
-- Dumped by pg_dump version 18.1

-- Started on 2026-11-18 17:31:10

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 920 (class 1247 OID 16774)
-- Name: payment_method_enum; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.payment_method_enum AS ENUM (
    'QRIS'
);


ALTER TYPE public.payment_method_enum OWNER TO postgres;

--
-- TOC entry 923 (class 1247 OID 16778)
-- Name: payment_status_enum; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.payment_status_enum AS ENUM (
    'Menunggu',
    'Lunas',
    'Gagal',
    'Kedaluwarsa',
    'Dibatalkan'
);


ALTER TYPE public.payment_status_enum OWNER TO postgres;

--
-- TOC entry 932 (class 1247 OID 16854)
-- Name: training_category_enum; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.training_category_enum AS ENUM (
    'Technical',
    'Nontechnical'
);


ALTER TYPE public.training_category_enum OWNER TO postgres;

--
-- TOC entry 887 (class 1247 OID 16594)
-- Name: training_interaction_enum; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.training_interaction_enum AS ENUM (
    'Daring',
    'Luring'
);


ALTER TYPE public.training_interaction_enum OWNER TO postgres;

--
-- TOC entry 914 (class 1247 OID 16743)
-- Name: training_online_type_enum; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.training_online_type_enum AS ENUM (
    'Asinkron',
    'Sinkron'
);


ALTER TYPE public.training_online_type_enum OWNER TO postgres;

--
-- TOC entry 917 (class 1247 OID 16752)
-- Name: transaction_status_enum; Type: TYPE; Schema: public; Owner: postgres
--

CREATE TYPE public.transaction_status_enum AS ENUM (
    'Menunggu Pembayaran',
    'Terdaftar',
    'Dibatalkan'
);


ALTER TYPE public.transaction_status_enum OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 219 (class 1259 OID 16599)
-- Name: accounts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.accounts (
    account_id integer NOT NULL,
    account_username character varying(50) NOT NULL,
    account_email character varying(255) NOT NULL,
    account_phone character varying(20),
    account_pass character varying(255) NOT NULL,
    role_id integer NOT NULL,
    account_full_name character varying(150)
);


ALTER TABLE public.accounts OWNER TO postgres;

--
-- TOC entry 220 (class 1259 OID 16609)
-- Name: accounts_account_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.accounts ALTER COLUMN account_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.accounts_account_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 252 (class 1259 OID 17035)
-- Name: attendance_records; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.attendance_records (
    attendance_id integer NOT NULL,
    transaction_id integer NOT NULL,
    attendance_status character varying(30) DEFAULT 'Belum Dikirim'::character varying NOT NULL,
    attendance_evidence_type character varying(30),
    attendance_evidence_path text,
    attendance_submitted_at timestamp without time zone,
    attendance_verified_by integer,
    attendance_verified_at timestamp without time zone,
    attendance_notes text,
    CONSTRAINT attendance_records_attendance_evidence_type_check CHECK (((attendance_evidence_type)::text = ANY ((ARRAY['Foto Kelas'::character varying, 'Screenshot Meeting'::character varying, 'Verifikasi Manual'::character varying])::text[]))),
    CONSTRAINT attendance_records_attendance_status_check CHECK (((attendance_status)::text = ANY ((ARRAY['Belum Dikirim'::character varying, 'Menunggu Verifikasi'::character varying, 'Hadir'::character varying, 'Tidak Hadir'::character varying])::text[])))
);


ALTER TABLE public.attendance_records OWNER TO postgres;

--
-- TOC entry 251 (class 1259 OID 17034)
-- Name: attendance_records_attendance_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.attendance_records_attendance_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_records_attendance_id_seq OWNER TO postgres;

--
-- TOC entry 5250 (class 0 OID 0)
-- Dependencies: 251
-- Name: attendance_records_attendance_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.attendance_records_attendance_id_seq OWNED BY public.attendance_records.attendance_id;


--
-- TOC entry 250 (class 1259 OID 16998)
-- Name: certificates; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.certificates (
    certificate_id integer NOT NULL,
    transaction_id integer NOT NULL,
    attempt_id integer NOT NULL,
    certificate_number character varying(60) NOT NULL,
    certificate_name character varying(150) NOT NULL,
    certificate_training_title character varying(200) NOT NULL,
    certificate_issued_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.certificates OWNER TO postgres;

--
-- TOC entry 249 (class 1259 OID 16997)
-- Name: certificates_certificate_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.certificates_certificate_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.certificates_certificate_id_seq OWNER TO postgres;

--
-- TOC entry 5251 (class 0 OID 0)
-- Dependencies: 249
-- Name: certificates_certificate_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.certificates_certificate_id_seq OWNED BY public.certificates.certificate_id;


--
-- TOC entry 254 (class 1259 OID 17062)
-- Name: contact_inquiries; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.contact_inquiries (
    inquiry_id integer NOT NULL,
    account_id integer,
    inquiry_email character varying(254) NOT NULL,
    inquiry_message text NOT NULL,
    inquiry_status character varying(20) DEFAULT 'Baru'::character varying NOT NULL,
    inquiry_created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    inquiry_updated_at timestamp without time zone,
    CONSTRAINT chk_inquiry_status CHECK (((inquiry_status)::text = ANY ((ARRAY['Baru'::character varying, 'Diproses'::character varying, 'Dijawab'::character varying, 'Ditutup'::character varying])::text[])))
);


ALTER TABLE public.contact_inquiries OWNER TO postgres;

--
-- TOC entry 253 (class 1259 OID 17061)
-- Name: contact_inquiries_inquiry_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.contact_inquiries ALTER COLUMN inquiry_id ADD GENERATED BY DEFAULT AS IDENTITY (
    SEQUENCE NAME public.contact_inquiries_inquiry_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 238 (class 1259 OID 16833)
-- Name: module_progress; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.module_progress (
    module_progress_id integer NOT NULL,
    transaction_id integer NOT NULL,
    module_id integer NOT NULL,
    module_completed_at timestamp without time zone
);


ALTER TABLE public.module_progress OWNER TO postgres;

--
-- TOC entry 237 (class 1259 OID 16832)
-- Name: module_progress_module_progress_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.module_progress ALTER COLUMN module_progress_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.module_progress_module_progress_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 221 (class 1259 OID 16610)
-- Name: payments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.payments (
    payment_id integer NOT NULL,
    payment_method public.payment_method_enum NOT NULL,
    payment_status public.payment_status_enum DEFAULT 'Menunggu'::public.payment_status_enum NOT NULL,
    payment_amount numeric(10,2) NOT NULL,
    payment_reference character varying(100),
    transaction_id integer NOT NULL,
    payment_provider character varying(30) DEFAULT 'Midtrans'::character varying NOT NULL,
    payment_order_id character varying(100) NOT NULL,
    payment_provider_status character varying(30),
    payment_qr_url text,
    payment_created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    payment_expiry_at timestamp without time zone,
    payment_paid_at timestamp without time zone,
    CONSTRAINT chk_payment_amount CHECK ((payment_amount > (0)::numeric))
);


ALTER TABLE public.payments OWNER TO postgres;

--
-- TOC entry 222 (class 1259 OID 16620)
-- Name: payments_payment_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.payments ALTER COLUMN payment_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.payments_payment_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 248 (class 1259 OID 16969)
-- Name: posttest_answers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.posttest_answers (
    answer_id integer NOT NULL,
    attempt_id integer NOT NULL,
    question_id integer NOT NULL,
    selected_option_id integer NOT NULL,
    answer_is_correct boolean NOT NULL
);


ALTER TABLE public.posttest_answers OWNER TO postgres;

--
-- TOC entry 247 (class 1259 OID 16968)
-- Name: posttest_answers_answer_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.posttest_answers_answer_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.posttest_answers_answer_id_seq OWNER TO postgres;

--
-- TOC entry 5252 (class 0 OID 0)
-- Dependencies: 247
-- Name: posttest_answers_answer_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.posttest_answers_answer_id_seq OWNED BY public.posttest_answers.answer_id;


--
-- TOC entry 246 (class 1259 OID 16942)
-- Name: posttest_attempts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.posttest_attempts (
    attempt_id integer NOT NULL,
    transaction_id integer NOT NULL,
    posttest_id integer NOT NULL,
    attempt_score numeric(5,2) NOT NULL,
    attempt_is_passed boolean NOT NULL,
    attempt_submitted_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    attempt_number integer DEFAULT 1 NOT NULL,
    CONSTRAINT posttest_attempts_attempt_number_positive CHECK ((attempt_number > 0)),
    CONSTRAINT posttest_attempts_attempt_score_check CHECK (((attempt_score >= (0)::numeric) AND (attempt_score <= (100)::numeric)))
);


ALTER TABLE public.posttest_attempts OWNER TO postgres;

--
-- TOC entry 245 (class 1259 OID 16941)
-- Name: posttest_attempts_attempt_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.posttest_attempts_attempt_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.posttest_attempts_attempt_id_seq OWNER TO postgres;

--
-- TOC entry 5253 (class 0 OID 0)
-- Dependencies: 245
-- Name: posttest_attempts_attempt_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.posttest_attempts_attempt_id_seq OWNED BY public.posttest_attempts.attempt_id;


--
-- TOC entry 244 (class 1259 OID 16916)
-- Name: posttest_options; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.posttest_options (
    option_id integer NOT NULL,
    question_id integer NOT NULL,
    option_label character(1) NOT NULL,
    option_text text NOT NULL,
    option_is_correct boolean DEFAULT false NOT NULL,
    CONSTRAINT posttest_options_option_label_check CHECK ((option_label = ANY (ARRAY['A'::bpchar, 'B'::bpchar, 'C'::bpchar, 'D'::bpchar])))
);


ALTER TABLE public.posttest_options OWNER TO postgres;

--
-- TOC entry 243 (class 1259 OID 16915)
-- Name: posttest_options_option_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.posttest_options_option_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.posttest_options_option_id_seq OWNER TO postgres;

--
-- TOC entry 5254 (class 0 OID 0)
-- Dependencies: 243
-- Name: posttest_options_option_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.posttest_options_option_id_seq OWNED BY public.posttest_options.option_id;


--
-- TOC entry 242 (class 1259 OID 16895)
-- Name: posttest_questions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.posttest_questions (
    question_id integer NOT NULL,
    posttest_id integer NOT NULL,
    question_order integer NOT NULL,
    question_text text NOT NULL,
    CONSTRAINT posttest_questions_question_order_check CHECK ((question_order > 0))
);


ALTER TABLE public.posttest_questions OWNER TO postgres;

--
-- TOC entry 241 (class 1259 OID 16894)
-- Name: posttest_questions_question_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.posttest_questions_question_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.posttest_questions_question_id_seq OWNER TO postgres;

--
-- TOC entry 5255 (class 0 OID 0)
-- Dependencies: 241
-- Name: posttest_questions_question_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.posttest_questions_question_id_seq OWNED BY public.posttest_questions.question_id;


--
-- TOC entry 240 (class 1259 OID 16871)
-- Name: posttests; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.posttests (
    posttest_id integer NOT NULL,
    training_id integer NOT NULL,
    posttest_title character varying(150) NOT NULL,
    posttest_passing_score numeric(5,2) DEFAULT 70 NOT NULL,
    posttest_is_active boolean DEFAULT false NOT NULL,
    posttest_created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    CONSTRAINT posttests_posttest_passing_score_check CHECK (((posttest_passing_score >= (0)::numeric) AND (posttest_passing_score <= (100)::numeric)))
);


ALTER TABLE public.posttests OWNER TO postgres;

--
-- TOC entry 239 (class 1259 OID 16870)
-- Name: posttests_posttest_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.posttests_posttest_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.posttests_posttest_id_seq OWNER TO postgres;

--
-- TOC entry 5256 (class 0 OID 0)
-- Dependencies: 239
-- Name: posttests_posttest_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.posttests_posttest_id_seq OWNED BY public.posttests.posttest_id;


--
-- TOC entry 223 (class 1259 OID 16621)
-- Name: rekening_tujuan; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.rekening_tujuan (
    rekening_tujuan_id integer NOT NULL,
    rekening_tujuan_bank character varying(50) NOT NULL,
    rekening_tujuan_number character varying(50) NOT NULL
);


ALTER TABLE public.rekening_tujuan OWNER TO postgres;

--
-- TOC entry 224 (class 1259 OID 16627)
-- Name: rekening_tujuan_rekening_tujuan_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.rekening_tujuan ALTER COLUMN rekening_tujuan_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.rekening_tujuan_rekening_tujuan_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 225 (class 1259 OID 16628)
-- Name: roles; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.roles (
    role_id integer NOT NULL,
    role_name character varying(50) NOT NULL
);


ALTER TABLE public.roles OWNER TO postgres;

--
-- TOC entry 226 (class 1259 OID 16633)
-- Name: roles_role_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.roles ALTER COLUMN role_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.roles_role_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 227 (class 1259 OID 16634)
-- Name: schedules; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.schedules (
    schedule_id integer NOT NULL,
    schedule_start_date date NOT NULL,
    schedule_duration integer NOT NULL,
    schedule_start_time time without time zone,
    schedule_end_time time without time zone,
    schedule_link character varying(255),
    schedule_location character varying(100),
    training_id integer NOT NULL,
    schedule_posttest_open_at timestamp without time zone,
    schedule_posttest_close_at timestamp without time zone,
    CONSTRAINT chk_schedule_duration CHECK ((schedule_duration > 0)),
    CONSTRAINT chk_schedule_link_location CHECK (((schedule_link IS NULL) OR (schedule_location IS NULL))),
    CONSTRAINT chk_schedule_posttest_window CHECK ((((schedule_posttest_open_at IS NULL) AND (schedule_posttest_close_at IS NULL)) OR ((schedule_posttest_open_at IS NOT NULL) AND (schedule_posttest_close_at IS NOT NULL) AND (schedule_posttest_open_at < schedule_posttest_close_at)))),
    CONSTRAINT chk_schedule_time_pair CHECK ((((schedule_start_time IS NULL) AND (schedule_end_time IS NULL)) OR ((schedule_start_time IS NOT NULL) AND (schedule_end_time IS NOT NULL))))
);


ALTER TABLE public.schedules OWNER TO postgres;

--
-- TOC entry 228 (class 1259 OID 16644)
-- Name: schedules_schedule_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.schedules ALTER COLUMN schedule_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.schedules_schedule_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 229 (class 1259 OID 16645)
-- Name: trainers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.trainers (
    trainer_id integer NOT NULL,
    trainer_name character varying(50) NOT NULL,
    trainer_background text NOT NULL,
    trainer_experience text NOT NULL
);


ALTER TABLE public.trainers OWNER TO postgres;

--
-- TOC entry 230 (class 1259 OID 16654)
-- Name: trainers_trainer_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.trainers ALTER COLUMN trainer_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.trainers_trainer_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 236 (class 1259 OID 16813)
-- Name: training_modules; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.training_modules (
    module_id integer NOT NULL,
    training_id integer NOT NULL,
    module_order integer NOT NULL,
    module_title character varying(150) NOT NULL,
    module_description text,
    module_content text,
    module_material_url text,
    CONSTRAINT chk_module_order CHECK ((module_order > 0))
);


ALTER TABLE public.training_modules OWNER TO postgres;

--
-- TOC entry 235 (class 1259 OID 16812)
-- Name: training_modules_module_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.training_modules ALTER COLUMN module_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.training_modules_module_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 231 (class 1259 OID 16655)
-- Name: trainings; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.trainings (
    training_id integer NOT NULL,
    training_topic character varying(50) NOT NULL,
    training_material text NOT NULL,
    training_target_audience character varying(50) NOT NULL,
    training_category public.training_category_enum NOT NULL,
    training_fee numeric(10,2) NOT NULL,
    training_benefit text NOT NULL,
    training_interaction public.training_interaction_enum NOT NULL,
    trainer_id integer NOT NULL,
    training_code character varying(30) NOT NULL,
    training_online_type public.training_online_type_enum,
    CONSTRAINT chk_training_fee CHECK ((training_fee >= (0)::numeric)),
    CONSTRAINT chk_training_online_type CHECK ((((training_interaction = 'Luring'::public.training_interaction_enum) AND (training_online_type IS NULL)) OR ((training_interaction = 'Daring'::public.training_interaction_enum) AND (training_online_type IS NOT NULL))))
);


ALTER TABLE public.trainings OWNER TO postgres;

--
-- TOC entry 232 (class 1259 OID 16670)
-- Name: trainings_training_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.trainings ALTER COLUMN training_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.trainings_training_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 233 (class 1259 OID 16671)
-- Name: transactions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.transactions (
    transaction_id integer NOT NULL,
    transaction_date date DEFAULT CURRENT_DATE NOT NULL,
    transaction_time time without time zone DEFAULT LOCALTIME NOT NULL,
    account_id integer NOT NULL,
    schedule_id integer NOT NULL,
    rekening_tujuan_id integer,
    transaction_amount numeric(10,2) NOT NULL,
    transaction_status public.transaction_status_enum DEFAULT 'Menunggu Pembayaran'::public.transaction_status_enum NOT NULL,
    CONSTRAINT chk_transaction_amount CHECK ((transaction_amount >= (0)::numeric))
);


ALTER TABLE public.transactions OWNER TO postgres;

--
-- TOC entry 234 (class 1259 OID 16682)
-- Name: transactions_transaction_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

ALTER TABLE public.transactions ALTER COLUMN transaction_id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.transactions_transaction_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- TOC entry 4978 (class 2604 OID 17038)
-- Name: attendance_records attendance_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records ALTER COLUMN attendance_id SET DEFAULT nextval('public.attendance_records_attendance_id_seq'::regclass);


--
-- TOC entry 4976 (class 2604 OID 17001)
-- Name: certificates certificate_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.certificates ALTER COLUMN certificate_id SET DEFAULT nextval('public.certificates_certificate_id_seq'::regclass);


--
-- TOC entry 4975 (class 2604 OID 16972)
-- Name: posttest_answers answer_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_answers ALTER COLUMN answer_id SET DEFAULT nextval('public.posttest_answers_answer_id_seq'::regclass);


--
-- TOC entry 4972 (class 2604 OID 16945)
-- Name: posttest_attempts attempt_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_attempts ALTER COLUMN attempt_id SET DEFAULT nextval('public.posttest_attempts_attempt_id_seq'::regclass);


--
-- TOC entry 4970 (class 2604 OID 16919)
-- Name: posttest_options option_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_options ALTER COLUMN option_id SET DEFAULT nextval('public.posttest_options_option_id_seq'::regclass);


--
-- TOC entry 4969 (class 2604 OID 16898)
-- Name: posttest_questions question_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_questions ALTER COLUMN question_id SET DEFAULT nextval('public.posttest_questions_question_id_seq'::regclass);


--
-- TOC entry 4965 (class 2604 OID 16874)
-- Name: posttests posttest_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttests ALTER COLUMN posttest_id SET DEFAULT nextval('public.posttests_posttest_id_seq'::regclass);


--
-- TOC entry 5000 (class 2606 OID 16684)
-- Name: accounts accounts_account_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_account_email_key UNIQUE (account_email);


--
-- TOC entry 5002 (class 2606 OID 16686)
-- Name: accounts accounts_account_username_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_account_username_key UNIQUE (account_username);


--
-- TOC entry 5004 (class 2606 OID 16688)
-- Name: accounts accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_pkey PRIMARY KEY (account_id);


--
-- TOC entry 5067 (class 2606 OID 17048)
-- Name: attendance_records attendance_records_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_pkey PRIMARY KEY (attendance_id);


--
-- TOC entry 5069 (class 2606 OID 17050)
-- Name: attendance_records attendance_records_transaction_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_transaction_id_key UNIQUE (transaction_id);


--
-- TOC entry 5059 (class 2606 OID 17015)
-- Name: certificates certificates_attempt_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.certificates
    ADD CONSTRAINT certificates_attempt_id_key UNIQUE (attempt_id);


--
-- TOC entry 5061 (class 2606 OID 17017)
-- Name: certificates certificates_certificate_number_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.certificates
    ADD CONSTRAINT certificates_certificate_number_key UNIQUE (certificate_number);


--
-- TOC entry 5063 (class 2606 OID 17011)
-- Name: certificates certificates_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.certificates
    ADD CONSTRAINT certificates_pkey PRIMARY KEY (certificate_id);


--
-- TOC entry 5065 (class 2606 OID 17013)
-- Name: certificates certificates_transaction_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.certificates
    ADD CONSTRAINT certificates_transaction_id_key UNIQUE (transaction_id);


--
-- TOC entry 5071 (class 2606 OID 17076)
-- Name: contact_inquiries contact_inquiries_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.contact_inquiries
    ADD CONSTRAINT contact_inquiries_pkey PRIMARY KEY (inquiry_id);


--
-- TOC entry 5032 (class 2606 OID 16840)
-- Name: module_progress module_progress_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.module_progress
    ADD CONSTRAINT module_progress_pkey PRIMARY KEY (module_progress_id);


--
-- TOC entry 5006 (class 2606 OID 16806)
-- Name: payments payments_payment_order_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_payment_order_id_key UNIQUE (payment_order_id);


--
-- TOC entry 5008 (class 2606 OID 16690)
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (payment_id);


--
-- TOC entry 5055 (class 2606 OID 16981)
-- Name: posttest_answers posttest_answers_attempt_id_question_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_answers
    ADD CONSTRAINT posttest_answers_attempt_id_question_id_key UNIQUE (attempt_id, question_id);


--
-- TOC entry 5057 (class 2606 OID 16979)
-- Name: posttest_answers posttest_answers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_answers
    ADD CONSTRAINT posttest_answers_pkey PRIMARY KEY (answer_id);


--
-- TOC entry 5051 (class 2606 OID 16955)
-- Name: posttest_attempts posttest_attempts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_attempts
    ADD CONSTRAINT posttest_attempts_pkey PRIMARY KEY (attempt_id);


--
-- TOC entry 5044 (class 2606 OID 16930)
-- Name: posttest_options posttest_options_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_options
    ADD CONSTRAINT posttest_options_pkey PRIMARY KEY (option_id);


--
-- TOC entry 5046 (class 2606 OID 16934)
-- Name: posttest_options posttest_options_question_id_option_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_options
    ADD CONSTRAINT posttest_options_question_id_option_id_key UNIQUE (question_id, option_id);


--
-- TOC entry 5048 (class 2606 OID 16932)
-- Name: posttest_options posttest_options_question_id_option_label_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_options
    ADD CONSTRAINT posttest_options_question_id_option_label_key UNIQUE (question_id, option_label);


--
-- TOC entry 5040 (class 2606 OID 16907)
-- Name: posttest_questions posttest_questions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_questions
    ADD CONSTRAINT posttest_questions_pkey PRIMARY KEY (question_id);


--
-- TOC entry 5042 (class 2606 OID 16909)
-- Name: posttest_questions posttest_questions_posttest_id_question_order_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_questions
    ADD CONSTRAINT posttest_questions_posttest_id_question_order_key UNIQUE (posttest_id, question_order);


--
-- TOC entry 5036 (class 2606 OID 16886)
-- Name: posttests posttests_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttests
    ADD CONSTRAINT posttests_pkey PRIMARY KEY (posttest_id);


--
-- TOC entry 5038 (class 2606 OID 16888)
-- Name: posttests posttests_training_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttests
    ADD CONSTRAINT posttests_training_id_key UNIQUE (training_id);


--
-- TOC entry 5011 (class 2606 OID 16694)
-- Name: rekening_tujuan rekening_tujuan_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.rekening_tujuan
    ADD CONSTRAINT rekening_tujuan_pkey PRIMARY KEY (rekening_tujuan_id);


--
-- TOC entry 5013 (class 2606 OID 16696)
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (role_id);


--
-- TOC entry 5015 (class 2606 OID 16698)
-- Name: roles roles_role_name_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_role_name_key UNIQUE (role_name);


--
-- TOC entry 5017 (class 2606 OID 16700)
-- Name: schedules schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.schedules
    ADD CONSTRAINT schedules_pkey PRIMARY KEY (schedule_id);


--
-- TOC entry 5019 (class 2606 OID 16702)
-- Name: trainers trainers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trainers
    ADD CONSTRAINT trainers_pkey PRIMARY KEY (trainer_id);


--
-- TOC entry 5028 (class 2606 OID 16824)
-- Name: training_modules training_modules_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.training_modules
    ADD CONSTRAINT training_modules_pkey PRIMARY KEY (module_id);


--
-- TOC entry 5021 (class 2606 OID 16704)
-- Name: trainings trainings_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trainings
    ADD CONSTRAINT trainings_pkey PRIMARY KEY (training_id);


--
-- TOC entry 5025 (class 2606 OID 16706)
-- Name: transactions transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT transactions_pkey PRIMARY KEY (transaction_id);


--
-- TOC entry 5030 (class 2606 OID 16826)
-- Name: training_modules uq_module_order; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.training_modules
    ADD CONSTRAINT uq_module_order UNIQUE (training_id, module_order);


--
-- TOC entry 5053 (class 2606 OID 17032)
-- Name: posttest_attempts uq_posttest_attempt_transaction_number; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_attempts
    ADD CONSTRAINT uq_posttest_attempt_transaction_number UNIQUE (transaction_id, attempt_number);


--
-- TOC entry 5023 (class 2606 OID 16749)
-- Name: trainings uq_trainings_training_code; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trainings
    ADD CONSTRAINT uq_trainings_training_code UNIQUE (training_code);


--
-- TOC entry 5034 (class 2606 OID 16842)
-- Name: module_progress uq_transaction_module; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.module_progress
    ADD CONSTRAINT uq_transaction_module UNIQUE (transaction_id, module_id);


--
-- TOC entry 5072 (class 1259 OID 17082)
-- Name: idx_contact_inquiries_account; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_contact_inquiries_account ON public.contact_inquiries USING btree (account_id);


--
-- TOC entry 5073 (class 1259 OID 17084)
-- Name: idx_contact_inquiries_created; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_contact_inquiries_created ON public.contact_inquiries USING btree (inquiry_created_at DESC);


--
-- TOC entry 5074 (class 1259 OID 17083)
-- Name: idx_contact_inquiries_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_contact_inquiries_status ON public.contact_inquiries USING btree (inquiry_status);


--
-- TOC entry 5026 (class 1259 OID 16869)
-- Name: uq_active_registration_per_member_schedule; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_active_registration_per_member_schedule ON public.transactions USING btree (account_id, schedule_id) WHERE (transaction_status <> 'Dibatalkan'::public.transaction_status_enum);


--
-- TOC entry 5009 (class 1259 OID 16810)
-- Name: uq_one_successful_payment_per_transaction; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_one_successful_payment_per_transaction ON public.payments USING btree (transaction_id) WHERE (payment_status = 'Lunas'::public.payment_status_enum);


--
-- TOC entry 5049 (class 1259 OID 16940)
-- Name: uq_posttest_one_correct_option; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_posttest_one_correct_option ON public.posttest_options USING btree (question_id) WHERE (option_is_correct = true);


--
-- TOC entry 5095 (class 2606 OID 17056)
-- Name: attendance_records attendance_records_attendance_verified_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_attendance_verified_by_fkey FOREIGN KEY (attendance_verified_by) REFERENCES public.accounts(account_id);


--
-- TOC entry 5096 (class 2606 OID 17051)
-- Name: attendance_records attendance_records_transaction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.attendance_records
    ADD CONSTRAINT attendance_records_transaction_id_fkey FOREIGN KEY (transaction_id) REFERENCES public.transactions(transaction_id);


--
-- TOC entry 5093 (class 2606 OID 17023)
-- Name: certificates certificates_attempt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.certificates
    ADD CONSTRAINT certificates_attempt_id_fkey FOREIGN KEY (attempt_id) REFERENCES public.posttest_attempts(attempt_id);


--
-- TOC entry 5094 (class 2606 OID 17018)
-- Name: certificates certificates_transaction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.certificates
    ADD CONSTRAINT certificates_transaction_id_fkey FOREIGN KEY (transaction_id) REFERENCES public.transactions(transaction_id);


--
-- TOC entry 5075 (class 2606 OID 16707)
-- Name: accounts fk_accounts_roles; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT fk_accounts_roles FOREIGN KEY (role_id) REFERENCES public.roles(role_id);


--
-- TOC entry 5097 (class 2606 OID 17077)
-- Name: contact_inquiries fk_inquiry_account; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.contact_inquiries
    ADD CONSTRAINT fk_inquiry_account FOREIGN KEY (account_id) REFERENCES public.accounts(account_id) ON DELETE SET NULL;


--
-- TOC entry 5082 (class 2606 OID 16827)
-- Name: training_modules fk_modules_trainings; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.training_modules
    ADD CONSTRAINT fk_modules_trainings FOREIGN KEY (training_id) REFERENCES public.trainings(training_id);


--
-- TOC entry 5076 (class 2606 OID 16712)
-- Name: payments fk_payments_transactions; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT fk_payments_transactions FOREIGN KEY (transaction_id) REFERENCES public.transactions(transaction_id);


--
-- TOC entry 5083 (class 2606 OID 16848)
-- Name: module_progress fk_progress_modules; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.module_progress
    ADD CONSTRAINT fk_progress_modules FOREIGN KEY (module_id) REFERENCES public.training_modules(module_id);


--
-- TOC entry 5084 (class 2606 OID 16843)
-- Name: module_progress fk_progress_transactions; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.module_progress
    ADD CONSTRAINT fk_progress_transactions FOREIGN KEY (transaction_id) REFERENCES public.transactions(transaction_id);


--
-- TOC entry 5077 (class 2606 OID 16717)
-- Name: schedules fk_schedules_trainings; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.schedules
    ADD CONSTRAINT fk_schedules_trainings FOREIGN KEY (training_id) REFERENCES public.trainings(training_id);


--
-- TOC entry 5078 (class 2606 OID 16722)
-- Name: trainings fk_trainings_trainers; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.trainings
    ADD CONSTRAINT fk_trainings_trainers FOREIGN KEY (trainer_id) REFERENCES public.trainers(trainer_id);


--
-- TOC entry 5079 (class 2606 OID 16727)
-- Name: transactions fk_transactions_accounts; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT fk_transactions_accounts FOREIGN KEY (account_id) REFERENCES public.accounts(account_id);


--
-- TOC entry 5080 (class 2606 OID 16732)
-- Name: transactions fk_transactions_rekening_tujuan; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT fk_transactions_rekening_tujuan FOREIGN KEY (rekening_tujuan_id) REFERENCES public.rekening_tujuan(rekening_tujuan_id);


--
-- TOC entry 5081 (class 2606 OID 16737)
-- Name: transactions fk_transactions_schedules; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT fk_transactions_schedules FOREIGN KEY (schedule_id) REFERENCES public.schedules(schedule_id);


--
-- TOC entry 5090 (class 2606 OID 16982)
-- Name: posttest_answers posttest_answers_attempt_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_answers
    ADD CONSTRAINT posttest_answers_attempt_id_fkey FOREIGN KEY (attempt_id) REFERENCES public.posttest_attempts(attempt_id);


--
-- TOC entry 5091 (class 2606 OID 16987)
-- Name: posttest_answers posttest_answers_question_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_answers
    ADD CONSTRAINT posttest_answers_question_id_fkey FOREIGN KEY (question_id) REFERENCES public.posttest_questions(question_id);


--
-- TOC entry 5092 (class 2606 OID 16992)
-- Name: posttest_answers posttest_answers_question_id_selected_option_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_answers
    ADD CONSTRAINT posttest_answers_question_id_selected_option_id_fkey FOREIGN KEY (question_id, selected_option_id) REFERENCES public.posttest_options(question_id, option_id);


--
-- TOC entry 5088 (class 2606 OID 16963)
-- Name: posttest_attempts posttest_attempts_posttest_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_attempts
    ADD CONSTRAINT posttest_attempts_posttest_id_fkey FOREIGN KEY (posttest_id) REFERENCES public.posttests(posttest_id);


--
-- TOC entry 5089 (class 2606 OID 16958)
-- Name: posttest_attempts posttest_attempts_transaction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_attempts
    ADD CONSTRAINT posttest_attempts_transaction_id_fkey FOREIGN KEY (transaction_id) REFERENCES public.transactions(transaction_id);


--
-- TOC entry 5087 (class 2606 OID 16935)
-- Name: posttest_options posttest_options_question_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_options
    ADD CONSTRAINT posttest_options_question_id_fkey FOREIGN KEY (question_id) REFERENCES public.posttest_questions(question_id);


--
-- TOC entry 5086 (class 2606 OID 16910)
-- Name: posttest_questions posttest_questions_posttest_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttest_questions
    ADD CONSTRAINT posttest_questions_posttest_id_fkey FOREIGN KEY (posttest_id) REFERENCES public.posttests(posttest_id);


--
-- TOC entry 5085 (class 2606 OID 16889)
-- Name: posttests posttests_training_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.posttests
    ADD CONSTRAINT posttests_training_id_fkey FOREIGN KEY (training_id) REFERENCES public.trainings(training_id);


-- Completed on 2026-11-18 17:31:10

--
-- PostgreSQL database dump complete
--

\unrestrict Cf1xdKThLUC4UypBhQxX9u29ByNgatO6gRTPcqpnZA5cflyUT8Hja9yo6mqTMej

