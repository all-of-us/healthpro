<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use App\Helper\MockUserHelper;
use App\Security\User;
use App\Tests\GoogleGroup;

class UserTest extends WebTestCase
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

    public function testReadOnlyGroups()
    {
        $email = 'user-test6@example.com';
        MockUserHelper::switchCurrentUser($email);
        $hpoGroup = User::SITE_PREFIX . 'my-siteA@gapps.com';
        $readOnlyGroup = User::READ_ONLY_GROUP . '@gapps.com';
        $groups = [
            new GoogleGroup($hpoGroup, 'Test Site 1', 'lorem ipsum 1'),
            new GoogleGroup($readOnlyGroup, 'Read Only View', 'lorem ipsum 2')
        ];
        $user = new User(MockUserHelper::getCurrentUser(), $groups);
        self::assertEquals($email, $user->getEmail());
        self::assertEquals(count($groups), count($user->getGroups()));
        self::assertEquals(1, count($user->getReadOnlyGroups()));
        self::assertEquals($groups[1]->getEmail(), $user->getReadOnlyGroups()[0]->email);
    }

    public function testGetGroup()
    {
        $email = 'user-test7@example.com';
        MockUserHelper::switchCurrentUser($email);
        $hpoGroup = User::SITE_PREFIX . 'my-siteA@gapps.com';
        $readOnlyGroup = User::READ_ONLY_GROUP . '@gapps.com';
        $groups = [
            new GoogleGroup($hpoGroup, 'Test Site 1', 'lorem ipsum 1'),
            new GoogleGroup($readOnlyGroup, 'Read Only View', 'lorem ipsum 2')
        ];
        $user = new User(MockUserHelper::getCurrentUser(), $groups);
        self::assertEquals('my-siteA', $user->getGroup($hpoGroup)->id);
        self::assertEquals(User::READ_ONLY_GROUP, $user->getGroup($readOnlyGroup)->id);
    }

    public function testGetGroupFromId()
    {
        $email = 'user-test8@example.com';
        MockUserHelper::switchCurrentUser($email);
        $hpoGroup = User::SITE_PREFIX . 'my-siteA@gapps.com';
        $readOnlyGroup = User::READ_ONLY_GROUP . '@gapps.com';
        $groups = [
            new GoogleGroup($hpoGroup, 'Test Site 1', 'lorem ipsum 1'),
            new GoogleGroup($readOnlyGroup, 'Read Only View', 'lorem ipsum 2')
        ];
        $user = new User(MockUserHelper::getCurrentUser(), $groups);
        self::assertEquals($hpoGroup, $user->getGroupFromId('my-siteA')->email);
        self::assertEquals($readOnlyGroup, $user->getGroupFromId(User::READ_ONLY_GROUP)->email);
    }



    public function testNphSites()
    {
        $email = 'user-test9@example.com';
        MockUserHelper::switchCurrentUser($email);
        $groups = [
            new GoogleGroup('test-group1@gapps.com', 'Test Group 1', 'lorem ipsum 1'),
            new GoogleGroup(User::SITE_NPH_PREFIX . 'my-siteA@gapps.com', 'Test Site 1', 'lorem ipsum 1'),
            new GoogleGroup('test-group2@gapps.com', 'Test Group 2', 'lorem ipsum 2'),
            new GoogleGroup(User::SITE_NPH_PREFIX . 'my-siteB@gapps.com', 'Test Site 2', 'lorem ipsum 2'),
        ];
        $user = new User(MockUserHelper::getCurrentUser(), $groups);
        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals(count($groups), count($user->getGroups()));
        $this->assertEquals(2, count($user->getNphSites()));
        $this->assertEquals($groups[1]->getEmail(), $user->getNphSites()[0]->email);
        $this->assertEquals($groups[3]->getName(), $user->getNphSites()[1]->name);
    }

    public function testGetNphSite()
    {
        $email = 'user-test4@example.com';
        MockUserHelper::switchCurrentUser($email);
        $groups = [
            new GoogleGroup(User::SITE_NPH_PREFIX . 'my-siteA@gapps.com', 'Test Site 1', 'lorem ipsum 1'),
            new GoogleGroup(User::SITE_NPH_PREFIX . 'my-siteB@gapps.com', 'Test Site 2', 'lorem ipsum 2'),
            new GoogleGroup(User::SITE_NPH_PREFIX . 'my-siteC@gapps.com', 'Test Site 3', 'lorem ipsum 3')
        ];
        $user = new User(MockUserHelper::getCurrentUser(), $groups);
        $this->assertEquals($groups[1]->getName(), $user->getSite($groups[1]->getEmail(), 'nphSites')->name);
    }

    public function testBelongsNphToSite()
    {
        $email = 'user-test5@example.com';
        MockUserHelper::switchCurrentUser($email);
        $groups = [
            new GoogleGroup(User::SITE_NPH_PREFIX . 'my-siteA@gapps.com', 'Test Site 1', 'lorem ipsum 1'),
            new GoogleGroup(User::SITE_NPH_PREFIX . 'my-siteB@gapps.com', 'Test Site 2', 'lorem ipsum 2'),
            new GoogleGroup(User::SITE_NPH_PREFIX . 'my-siteC@gapps.com', 'Test Site 3', 'lorem ipsum 3')
        ];
        $user = new User(MockUserHelper::getCurrentUser(), $groups);
        $this->assertEquals(true, $user->belongsToSite($groups[2]->getEmail(), 'nphSites'));
        $this->assertEquals(false, $user->belongsToSite(User::SITE_NPH_PREFIX . 'my-siteD@gapps.com', 'nphSites'));
    }
}
