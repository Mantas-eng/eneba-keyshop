CREATE TABLE IF NOT EXISTS games(id INTEGER PRIMARY KEY,name TEXT,platform TEXT,region TEXT,description TEXT);
CREATE TABLE IF NOT EXISTS offers(id INTEGER PRIMARY KEY,game INTEGER REFERENCES games(id),seller TEXT,cents INTEGER);
CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY,email TEXT UNIQUE,password TEXT);
CREATE TABLE IF NOT EXISTS orders(id INTEGER PRIMARY KEY,user INTEGER REFERENCES users(id),token TEXT,created TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(user,token));
CREATE TABLE IF NOT EXISTS items(id INTEGER PRIMARY KEY,order_id INTEGER REFERENCES orders(id),offer INTEGER REFERENCES offers(id),paid INTEGER,license TEXT UNIQUE);
CREATE TABLE IF NOT EXISTS banner(game INTEGER PRIMARY KEY,title TEXT,subtitle TEXT, image TEXT);
CREATE TABLE IF NOT EXISTS shelves(id INTEGER PRIMARY KEY,title TEXT);
CREATE TABLE IF NOT EXISTS picks(shelf INTEGER,game INTEGER,position INTEGER,PRIMARY KEY(shelf,game));
INSERT OR IGNORE INTO games VALUES
(1145360,'Hades','Steam','Global','Fight your way out of the Underworld. Each escape attempt brings new weapons, blessings and conversations.'),
(413150,'Stardew Valley','Steam','Global','Restore an overgrown farm, meet the townspeople and explore the mines at your own pace.'),
(367520,'Hollow Knight','Steam','Europe','Descend into Hallownest, a ruined kingdom of winding passages and strange inhabitants.'),
(504230,'Celeste','Xbox','Europe','Help Madeline climb Celeste Mountain through precise jumps and challenging single-screen stages.'),
(105600,'Terraria','Xbox','Global','Dig beneath the surface, build a home and face bosses in a procedurally generated world.'),
(588650,'Dead Cells','Steam','Europe','Explore a shifting island with fast combat and a new mix of weapons on each run.');
INSERT OR IGNORE INTO offers SELECT id*10+1,id,'Pixel Vault',CASE id WHEN 1145360 THEN 849 WHEN 413150 THEN 799 WHEN 367520 THEN 689 WHEN 504230 THEN 499 WHEN 105600 THEN 349 ELSE 929 END FROM games;
INSERT OR IGNORE INTO offers SELECT id+1,game,'Indie Market',cents+125 FROM offers WHERE id%10=1;
INSERT OR IGNORE INTO banner VALUES(1145360,'Escape the Underworld','Discover Hades','/art/hero.jpg');
INSERT OR IGNORE INTO shelves VALUES(1,'Indie essentials'),(2,'Under €8');
INSERT OR IGNORE INTO picks VALUES(1,1145360,1),(1,367520,2),(1,588650,3),(1,504230,4),(2,105600,1),(2,504230,2),(2,367520,3),(2,413150,4);

CREATE TABLE IF NOT EXISTS sessions(token TEXT PRIMARY KEY,user INTEGER NOT NULL REFERENCES users(id),expires INTEGER NOT NULL);
