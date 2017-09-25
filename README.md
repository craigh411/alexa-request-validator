# alexa-request-validator

A PHP implementation of the Alexa request validation required when using a web service with your Alexa skill, as set out in the [Alexa docs](https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service) 

## Installation

Install via composer by doing:

`composer require craigh/alexa-request-validator`

### How to use

Once installed you can instantiate the AlexaRequestValidator in your classes as follows:

```php
$validator = new \Humps\AlexaRequest\AlexaRequestValidator('YOUR_ALEXA_SKILL_ID', file_get_contents('php://input'), $_SERVER['HTTP_SIGNATURECERTCHAINURL'], $_SERVER['HTTP_SIGNATURE']);
```

Then simply do (Ideally you would add this in Middleware):

```php
try {
  if($validator->validateRequest()){
    // Accept request to your service
  }
  
  // Reject request with a 500 error, hopefully this shouldn't happen!
}catch(\Humps\AlexaRequest\Exceptions\AlexaRequestException $e) { 
  // Reject the request with a 400 error or the given error code (via $e->getCode()) with the returned message ($e->getMessage())
}
```


### Timeout Tolerance

By default the timeout tolerance is 120 seconds (well within the 150 seconds required by Amazon). If you want to adjust this you can pass a fifth paramater to the constructor:

```php
$validator = new AlexaRequestValidator('YOUR_ALEXA_SKILL_ID', file_get_contents('php://input'), $_SERVER['HTTP_SIGNATURECERTCHAINURL'], $_SERVER['HTTP_SIGNATURE'], 150);
```

> **Tip:** If you are testing through the Amazon Alexa Skill "test" section you may want to increase this tolerance during development so you can replay the request through your server. Just don't forget to reset it before certification!
