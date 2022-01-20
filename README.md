# manager

Manager::setup("Yanntyb\\Manager\\Model\\Classes\\Entity", $dbname, $dbuser, $dbpass);

$article = Manager::getSingleEntity("article",210);

$articles = Manager::getAllEntity("article");

$article = Manager::getSingleEntity("article","user_fk = :id", [":id" => 1]);
$articles = Manager::getAllEntity("article","user_fk = :id", [":id" => 1]);

$article->setContent("new content");

Manager::store($article);

$articles = Manager::getAllEntity("article");

foreach($articles as $article) {
    $article->setContent("test");
}

Manager::store($articles);