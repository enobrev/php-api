CREATE TABLE "addresses" (
  "address_id" INTEGER ,
  "user_id" int(10)  DEFAULT NULL,
  "address_line_1" varchar(100) DEFAULT NULL,
  "address_city" varchar(50) DEFAULT NULL,
  "address_date_added" datetime DEFAULT NULL,
  "address_date_updated" datetime DEFAULT NULL,
  PRIMARY KEY ("address_id")
);
CREATE TABLE "users" (
  "user_id" INTEGER ,
  "user_name" varchar(50) DEFAULT NULL,
  "user_email" varchar(100) DEFAULT NULL,
  "user_happy" tinyint(1)  NOT NULL DEFAULT '0',
  "user_date_added" datetime DEFAULT NULL,
  PRIMARY KEY ("user_id")
);
