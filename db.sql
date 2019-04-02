CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `pic` text,
  `oauth_provider` varchar(50) NOT NULL,
  `oauth_user_id` varchar(100) NOT NULL,
  `oauth_token` text NOT NULL,
  `registered_on` datetime NOT NULL,
  `logged_on` datetime DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `first_name` (`first_name`,`email`,`gender`,`oauth_provider`,`oauth_user_id`,`registered_on`,`logged_on`) USING BTREE;
