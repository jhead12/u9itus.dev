<?php 

/**
 * Inherited Methods
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method void haveFriend($name, $actorClass = null)
 *
 * @SuppressWarnings(PHPMD)
*/

$I = new FunctionalTester($scenario);

$I->am('guest');

$I->wantTo('signup a new user account');
$I->amOnPage('/');

$I->click('Sign Up!');

$I->seeCurrentUrlEquals('/register');
$I->fillField('firstName', 'John');
$I->fillField('lastName', 'Doe');
$I->fillField('email', 'john@example.com');
$I->fillField('username', 'JohnDoe');
$I->fillField('password', 'secret');
$I->fillField('password_again', 'secret');
$I->fillField('telephone','555-555-5555');
$I->fillField('sex','male');




$I->click('Create account');

$I->seeCurrentUrlEquals('/thankyou');




