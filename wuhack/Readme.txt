Install twilio from their site, follow their quickstart tutorial, etc. Key things to note is to change the AuthToken,AccountSID, and the ("from") field in the sendMessage() function to your specific account values.
MySQL and PHP are needed.
Money is in integer increments.
All accounts are phone numbers and were prepopulated into tables (like 15555555555).

Table structure:

 CREATE TABLE `loan` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `acct` bigint(20) unsigned NOT NULL,
  `backer1` bigint(20) unsigned NOT NULL,
  `prin` bigint(20) unsigned NOT NULL,
  `amnt` int(11) unsigned NOT NULL,
  `hash` varchar(200) NOT NULL,
  `paid` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
)


CREATE TABLE `acct` (
  `acct` bigint(20) unsigned NOT NULL,
  `balance` int(11) NOT NULL,
  PRIMARY KEY (`acct`)
)

CREATE TABLE `code` (
  `type` varchar(200) NOT NULL,
  `hash` varchar(200) NOT NULL,
  `acctTo` bigint(20) unsigned DEFAULT NULL,
  `acctFrom` bigint(20) unsigned DEFAULT NULL,
  `amnt` int(10) unsigned DEFAULT NULL,
  `backer1` bigint(20) unsigned DEFAULT NULL,
  `backer2` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`hash`)
)

Text commands to server:

Xfer <AMOUNT> <ACCOUNT> 			//transfer $<AMOUNT> from senders account to <ACCOUNT>
LoanApp <AMOUNT> <BACKER>			//submit a loan request for $<AMOUNT>, with <BACKER> guaranteeing 15% of the loan as collateral
PayLoan <HASH> 						//Pay loan <HASH>
MyLoan								//see loans of senders
Verify <HASH>						//verify transaction for <HASH>
LoanFul <HASH>                      //be a lender for the loan <HASH>
Search <MAX>						//Search for loans to back up to $<HASH>