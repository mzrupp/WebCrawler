/*
########### WEBCRAWLER DB SCRIPTS ###########

	WEBCRAWLER project of:
		- Niklas Rapp
		- Moritz Rupp
		
	USEABLE STATEMENTS
		- insert into TABLENAME(COLUMN, ...) values ("VALUE", ...);
		- delete from TABLENAME where RESTRICTION; 

--###########################################
*/

/* drop the webcrawler db if already exists */
drop database if exists WEBCRAWLER;

/* create new webcrawler db */
create database WEBCRAWLER;

/* select webcrawler db */
use WEBCRAWLER;

/* create table for saving urls */
create table URL
(
	ID			INTEGER PRIMARY KEY NOT NULL AUTO_INCREMENT,
	URL			VARCHAR(2048) NOT NULL,
	TIMESTAMP	TIMESTAMP NOT NULL,
	UNIQUE KEY url_unique_index (URL)
);

/*  create table for save words  */
create table WORD
(
	ID			INTEGER PRIMARY KEY NOT NULL AUTO_INCREMENT,
	WORD		VARCHAR(256) NOT NULL,
	UNIQUE KEY word_unique_index (WORD)
);

/* create table for linking urls and words */
create table LINK
(
	ID						INTEGER PRIMARY KEY NOT NULL AUTO_INCREMENT,
	URL_ID					INTEGER NOT NULL,
	WORD_ID					INTEGER NOT NULL,
	NUMBER_OF_WORDS_IN_URL	INTEGER NOT NULL,
    FOREIGN KEY (URL_ID) REFERENCES URL(ID),
	FOREIGN KEY (WORD_ID) REFERENCES WORD(ID),
	UNIQUE KEY ids_unique_index (URL_ID, WORD_ID)
);
