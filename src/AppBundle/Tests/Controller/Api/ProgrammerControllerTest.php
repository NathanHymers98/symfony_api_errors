<?php
namespace AppBundle\Tests\Controller\Api;

use AppBundle\Test\ApiTestCase;

class ProgrammerControllerTest extends ApiTestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->createUser('weaverryan');
    }

    public function testPOST()
    {
        $data = array(
            'nickname' => 'ObjectOrienter',
            'avatarNumber' => 5,
            'tagLine' => 'a test dev!'
        );

        // 1) Create a programmer resource
        $response = $this->client->post('/api/programmers', [
            'body' => json_encode($data)
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Location'));
        $this->assertStringEndsWith('/api/programmers/ObjectOrienter', $response->getHeader('Location'));
        $finishedData = json_decode($response->getBody(true), true);
        $this->assertArrayHasKey('nickname', $finishedData);
        $this->assertEquals('ObjectOrienter', $finishedData['nickname']);
    }

    public function testGETProgrammer()
    {
        $this->createProgrammer(array(
            'nickname' => 'UnitTester',
            'avatarNumber' => 3,
        ));

        $response = $this->client->get('/api/programmers/UnitTester');
        $this->assertEquals(200, $response->getStatusCode());
        $this->asserter()->assertResponsePropertiesExist($response, array(
            'nickname',
            'avatarNumber',
            'powerLevel',
            'tagLine'
        ));
        $this->asserter()->assertResponsePropertyEquals($response, 'nickname', 'UnitTester');
    }

    public function testGETProgrammersCollection()
    {
        $this->createProgrammer(array(
            'nickname' => 'UnitTester',
            'avatarNumber' => 3,
        ));
        $this->createProgrammer(array(
            'nickname' => 'CowboyCoder',
            'avatarNumber' => 5,
        ));

        $response = $this->client->get('/api/programmers');
        $this->assertEquals(200, $response->getStatusCode());
        $this->asserter()->assertResponsePropertyIsArray($response, 'programmers');
        $this->asserter()->assertResponsePropertyCount($response, 'programmers', 2);
        $this->asserter()->assertResponsePropertyEquals($response, 'programmers[1].nickname', 'CowboyCoder');
    }

    public function testPUTProgrammer()
    {
        $this->createProgrammer(array(
            'nickname' => 'CowboyCoder',
            'avatarNumber' => 5,
            'tagLine' => 'foo',
        ));

        $data = array(
            'nickname' => 'CowgirlCoder',
            'avatarNumber' => 2,
            'tagLine' => 'foo',
        );
        $response = $this->client->put('/api/programmers/CowboyCoder', [
            'body' => json_encode($data)
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->asserter()->assertResponsePropertyEquals($response, 'avatarNumber', 2);
        // the nickname is immutable on edit
        $this->asserter()->assertResponsePropertyEquals($response, 'nickname', 'CowboyCoder');
    }

    public function testPATCHProgrammer()
    {
        $this->createProgrammer(array(
            'nickname' => 'CowboyCoder',
            'avatarNumber' => 5,
            'tagLine' => 'foo',
        ));

        $data = array(
            'tagLine' => 'bar',
        );
        $response = $this->client->patch('/api/programmers/CowboyCoder', [
            'body' => json_encode($data)
        ]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->asserter()->assertResponsePropertyEquals($response, 'avatarNumber', 5);
        $this->asserter()->assertResponsePropertyEquals($response, 'tagLine', 'bar');
    }

    public function testDELETEProgrammer()
    {
        $this->createProgrammer(array(
            'nickname' => 'UnitTester',
            'avatarNumber' => 3,
        ));

        $response = $this->client->delete('/api/programmers/UnitTester');
        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testValidationErrors() // Testing validation errors
    {
        $data = array( // Creating an array of data which will be used to create a programmer resource, I am missing out the nickname field because this method is all about testing the validation for a missing field
            'avatarNumber' => 2,
            'tagLine' => 'I\'m from a test!'
        );

        // Creating the programmer resource with the data above
        $response = $this->client->post('/api/programmers', [
            'body' => json_encode($data)
        ]);

        $this->assertEquals(400, $response->getStatusCode()); // Asserting that the response status code for this test is 400
        $this->asserter()->assertResponsePropertiesExist($response, array( // Asserting that in the response body data, 3 properties exist
            'type', // The type property, which will display what type of error it is in a string error code format
            'title', // The title property, which will display a human description of what went wrong and caused the error
            'errors', // The errors property, which will display an array of the validaiton errors that happened
        ));
        $this->asserter()->assertResponsePropertyExists($response, 'errors.nickname'); // Asserting that the property 'errors.nickname exists' When a validaiton error happens, the response body data is an associative array called $errors so to target the keys we have to write 'errors.nickname' and the value of this key would be the error
        $this->asserter()->assertResponsePropertyEquals($response, 'errors.nickname[0]', 'Please enter a clever nickname'); // Asserting the exact value of the nickname property in the errors array, which should be the following error message
        $this->asserter()->assertResponsePropertyDoesNotExist($response, 'errors.avatarNumber'); // Asserting that there is no error for the avatarNumber key in the errors array.
        $this->assertEquals('application/problem+json', $response->getHeader('Content-Type')); // Asserting that we are getting the correct header response for the error, which should be 'application/problem+json'
    }

    public function testInvalidJson() // Testing when invalid JSON is passed
    {
        // This is simply creating bad JSON to use for this test
        $invalidBody = <<<EOF
{
    "nickname": "JohnnyRobot",
    "avatarNumber" : "2
    "tagLine": "I'm from a test!"
}
EOF;

        $response = $this->client->post('/api/programmers', [
            'body' => $invalidBody
        ]);

        $this->assertEquals(400, $response->getStatusCode());
        $this->asserter()->assertResponsePropertyContains($response, 'type', 'invalid_body_format'); // Asserting that the property 'type' in the response is equal to 'invalid_body_format'
    }

    public function test404Exception()
    {
        $response = $this->client->get('/api/programmers/fake');

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('application/problem+json', $response->getHeader('Content-Type'));
        $this->asserter()->assertResponsePropertyEquals($response, 'type', 'about:blank'); // Asserting that the type property is equal to about:blank
        $this->asserter()->assertResponsePropertyEquals($response, 'title', 'Not Found'); // Asserting that the title property is equal to Not Found
        $this->asserter()->assertResponsePropertyEquals($response, 'detail', 'No programmer found with nickname "fake"'); // Asserting that the detail property is equal to the following message
    }
}
