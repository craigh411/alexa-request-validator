<?php
use Humps\AlexaRequest\AlexaRequestValidator;
use PHPUnit\Framework\TestCase;

class AlexaRequestValidatorTest extends TestCase
{

    /**
     * @test
     */
    public function it_throws_an_exception_when_application_ids_do_not_match()
    {
        $this->expectException(Exception::class);
        //session->application->applicationId
        $request = ['session' => ['application' => ['applicationId' => 'bar']]];
        $validator = new AlexaRequestValidator('foo', json_encode($request), 'https://s3.amazonaws.com/echo.api/echo-api-cert.pem', 'foo');
        $validator->isValidApplicationId();
    }

    /**
     * @test
     */
    public function it_returns_true_when_the_application_ids_do_match()
    {
        $request = ['session' => ['application' => ['applicationId' => 'foo']]];
        $validator = new AlexaRequestValidator('foo', json_encode($request), 'https://s3.amazonaws.com/echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidApplicationId());
    }

    /**
     * @test
     */
    public function it_validates_the_amazon_signature_certificate_urls()
    {
        // URLS taken from: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com/echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());

        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com:443/echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());

        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com/echo.api/../echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());

        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com/echo.api/../echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());

        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com:443/echo.api/../echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());

    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_signature_certificate_url_has_invalid_protocol()
    {
        // URLS taken from: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
        $this->expectException(Exception::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'http://s3.amazonaws.com/echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());

    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_signature_certificate_url_has_invalid_hostname()
    {
        // URLS taken from: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
        $this->expectException(Exception::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'https://notamazon.com/echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());

    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_signature_certificate_url_has_non_lower_case_path()
    {
        // URLS taken from: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
        $this->expectException(Exception::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com/EcHo.aPi/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_signature_certificate_url_has_invalid_path()
    {
        // URLS taken from: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
        $this->expectException(Exception::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com/invalid.path/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_signature_certificate_url_has_invalid_port()
    {
        // URLS taken from: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
        $this->expectException(Exception::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com:563/echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_signature_certificate_url_has_invalid_port_and_requires_normalization()
    {
        // URLS taken from: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
        $this->expectException(Exception::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com:563/echo.api/../echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());
    }
}
