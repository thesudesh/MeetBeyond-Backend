-- USERS (boys)
INSERT INTO Users (email, password) VALUES
('boy1@demo.com', 'demo'),
('boy2@demo.com', 'demo'),
('boy3@demo.com', 'demo'),
('boy4@demo.com', 'demo'),
('boy5@demo.com', 'demo'),
('boy6@demo.com', 'demo'),
('boy7@demo.com', 'demo'),
('boy8@demo.com', 'demo'),
('boy9@demo.com', 'demo'),
('boy10@demo.com', 'demo'),
('boy11@demo.com', 'demo'),
('boy12@demo.com', 'demo'),
('boy13@demo.com', 'demo'),
('boy14@demo.com', 'demo'),
('boy15@demo.com', 'demo');

-- PROFILES (boys)
INSERT INTO Profiles (user_id, name, age, gender, ethnicity, interests, bio) VALUES
(1, 'Boy One', 24, 'male', 'Asian', 'football,music', 'Fun and outgoing!'),
(2, 'Boy Two', 27, 'male', 'White', 'gaming,cooking', 'Love to cook and play games.'),
(3, 'Boy Three', 22, 'male', 'Black', 'reading,travel', 'Avid traveler.'),
(4, 'Boy Four', 29, 'male', 'Latino', 'movies,fitness', 'Fitness is my passion.'),
(5, 'Boy Five', 25, 'male', 'Asian', 'tech,anime', 'Techie and anime fan.'),
(6, 'Boy Six', 23, 'male', 'White', 'music,sports', 'Guitarist and soccer player.'),
(7, 'Boy Seven', 28, 'male', 'Black', 'hiking,reading', 'Nature lover.'),
(8, 'Boy Eight', 26, 'male', 'Latino', 'dancing,travel', 'Let\'s go dancing!'),
(9, 'Boy Nine', 21, 'male', 'Asian', 'coding,gaming', 'Coder and gamer.'),
(10, 'Boy Ten', 24, 'male', 'White', 'swimming,art', 'Artist at heart.'),
(11, 'Boy Eleven', 22, 'male', 'Black', 'cooking,travel', 'Chef in training.'),
(12, 'Boy Twelve', 27, 'male', 'Latino', 'music,reading', 'Bookworm and music lover.'),
(13, 'Boy Thirteen', 28, 'male', 'Asian', 'tech,movies', 'Tech movies buff.'),
(14, 'Boy Fourteen', 25, 'male', 'White', 'fitness,football', 'Football enthusiast.'),
(15, 'Boy Fifteen', 23, 'male', 'Black', 'gaming,fitness', 'Gaming and fitness!');

-- PREFERENCES (boys)
INSERT INTO Preferences (user_id, min_age, max_age, gender_pref, location, relationship_type, interests) VALUES
(1, 20, 28, 'female', 'Kathmandu', 'dating', 'music,travel'),
(2, 22, 30, 'female', 'Kathmandu', 'long-term', 'cooking,gaming'),
(3, 20, 25, 'female', 'Pokhara', 'friendship', 'reading,travel'),
(4, 23, 29, 'female', 'Biratnagar', 'dating', 'fitness,movies'),
(5, 21, 26, 'female', 'Lalitpur', 'friendship', 'tech,anime'),
(6, 20, 27, 'female', 'Kathmandu', 'long-term', 'music,sports'),
(7, 22, 28, 'female', 'Pokhara', 'friendship', 'hiking,reading'),
(8, 23, 30, 'female', 'Biratnagar', 'dating', 'dancing,travel'),
(9, 19, 25, 'female', 'Lalitpur', 'friendship', 'coding,gaming'),
(10, 21, 26, 'female', 'Kathmandu', 'dating', 'swimming,art'),
(11, 20, 23, 'female', 'Pokhara', 'long-term', 'cooking,travel'),
(12, 22, 29, 'female', 'Biratnagar', 'friendship', 'music,reading'),
(13, 23, 28, 'female', 'Lalitpur', 'dating', 'tech,movies'),
(14, 21, 27, 'female', 'Kathmandu', 'long-term', 'fitness,football'),
(15, 19, 24, 'female', 'Pokhara', 'friendship', 'gaming,fitness');


