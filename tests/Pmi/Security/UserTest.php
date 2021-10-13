<?php
use App\Helper\MockUserHelper;
use App\Security\User;
use Tests\Pmi\GoogleGroup;

class UserTest extends PHPUnit\Framework\TestCase
{
    public function testEmail()
    {
        $email = 'user-test1@example.com';
        MockUserHelper::switchCurrentUser($email);
        $user = new User(MockUserHelper::getCurrentUser(), []);
        $this->assertEquals($email, $user->getEmail());
    }

    public function testNoGroups()
    {
        $email = 'user-test2@example.com';
        MockUserHelper::switchCurrentUser($email);
        $user = new User(MockUserHelper::getCurrentUser(), []);
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals([], $user->getGroups());
    }

    public function testGroups()
    {
        $email = 'user-test2@example.com';
        MockUserHelper::switchCurrentUser($email);
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup('test-group2@gapps.com', 'Test Group 2', 'lorem ipsum 2'),
            new GoogleGroup('test-group3@gapps.com', 'Test Group 3', 'lorem ipsum 3')
        ];
        $user = new User(MockUserHelper::getCurrentUser(), $groups);
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals(count($groups), count($user->getGroups()));
        $this->assertEquals($groups[1]->getName(), $user->getGroups()[1]->getName());
        // none of these should be considered sites
        $this->assertEquals(0, count($user->getSites()));
    }

    public function testSites()
    {
        $email = 'user-test3@example.com';
        MockUserHelper::switchCurrentUser($email);
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::SITE_PREFIX . 'my-siteA@gapps.com', 'Test Site 1', 'lorem ipsum 1'),
            new GoogleGroup('test-group2@gapps.com', 'Test Group 2', 'lorem ipsum 2'),
            new GoogleGroup(User::SITE_PREFIX . 'my-siteB@gapps.com', 'Test Site 2', 'lorem ipsum 2'),
        ];
        $user = new User(MockUserHelper::getCurrentUser(), $groups);
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals(count($groups), count($user->getGroups()));
        $this->assertEquals(2, count($user->getSites()));
        $this->assertEquals($groups[1]->getEmail(), $user->getSites()[0]->email);
        $this->assertEquals($groups[3]->getName(), $user->getSites()[1]->name);
    }

    public function testGetSite()
    {
        $email = 'user-test4@example.com';
        MockUserHelper::switchCurrentUser($email);
        $groups = [
            new GoogleGroup(User::SITE_PREFIX . 'my-siteA@gapps.com', 'Test Site 1', 'lorem ipsum 1'),
            new GoogleGroup(User::SITE_PREFIX . 'my-siteB@gapps.com', 'Test Site 2', 'lorem ipsum 2'),
            new GoogleGroup(User::SITE_PREFIX . 'my-siteC@gapps.com', 'Test Site 3', 'lorem ipsum 3')
        ];
        $user = new User(MockUserHelper::getCurrentUser(), $groups);
        $this->assertEquals($groups[1]->getName(), $user->getSite($groups[1]->getEmail())->name);
    }

    public function testBelongsToSite()
    {
        $email = 'user-test5@example.com';
        MockUserHelper::switchCurrentUser($email);
        $groups = [
            new GoogleGroup(User::SITE_PREFIX . 'my-siteA@gapps.com', 'Test Site 1', 'lorem ipsum 1'),
            new GoogleGroup(User::SITE_PREFIX . 'my-siteB@gapps.com', 'Test Site 2', 'lorem ipsum 2'),
            new GoogleGroup(User::SITE_PREFIX . 'my-siteC@gapps.com', 'Test Site 3', 'lorem ipsum 3')
        ];
        $user = new User(MockUserHelper::getCurrentUser(), $groups);
        $this->assertEquals(true, $user->belongsToSite($groups[2]->getEmail()));
        $this->assertEquals(false, $user->belongsToSite(User::SITE_PREFIX . 'my-siteD@gapps.com'));
    }
}
