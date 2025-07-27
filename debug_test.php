<?php

require_once 'vendor/autoload.php';

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DebugTest extends WebTestCase
{
    public function testLoginPageLinks()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $registerLinks = $crawler->filter('a[href*="register"]');
        echo "Found " . $registerLinks->count() . " register links:\n";

        $registerLinks->each(function ($node, $i) {
            echo "Link $i: " . trim($node->text()) . "\n";
            echo "  href: " . $node->attr('href') . "\n";
        });
    }

    public function testRegisterPageLinks()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/register');

        $loginLinks = $crawler->filter('a[href*="login"]');
        echo "Found " . $loginLinks->count() . " login links:\n";

        $loginLinks->each(function ($node, $i) {
            echo "Link $i: " . trim($node->text()) . "\n";
            echo "  href: " . $node->attr('href') . "\n";
        });
    }
}

$test = new DebugTest();
$test->testLoginPageLinks();
echo "\n";
$test->testRegisterPageLinks();
