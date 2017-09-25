<?php
use Humps\AlexaRequest\AlexaRequestValidator;
use Humps\AlexaRequest\Exceptions\AlexaValidationException;
use PHPUnit\Framework\TestCase;

class AlexaRequestValidatorTest extends TestCase
{

    // This may need to be updated when valid pem expires
    const VALID_PEM_URL = 'https://s3.amazonaws.com/echo.api/echo-api-cert-4.pem';

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
    public function it_throws_an_exception_when_application_ids_do_not_match()
    {
        $this->expectException(AlexaValidationException::class);
        //session->application->applicationId
        $request = ['session' => ['application' => ['applicationId' => 'bar']]];
        $validator = new AlexaRequestValidator('foo', json_encode($request), 'https://s3.amazonaws.com/echo.api/echo-api-cert.pem', 'foo');
        $validator->isValidApplicationId();
    }

    /**
     * @test
     */
    public function it_returns_true_when_the_request_has_a_valid_timestamp()
    {
        $date = date('Y-m-d\TH:i:s\Z');
        $request = ['request' => ['timestamp' => $date]];
        $validator = new AlexaRequestValidator('foo', json_encode($request), 'https://s3.amazonaws.com/echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->requestHasNotTimedOut());
    }

    /**
     * @test
     */
    public function it_returns_true_when_the_request_in_inside_the_timestamp_tolerance()
    {
        $date = date('Y-m-d\TH:i:s\Z') - 110;
        $request = ['request' => ['timestamp' => $date]];
        $validator = new AlexaRequestValidator('foo', json_encode($request), 'https://s3.amazonaws.com/echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->requestHasNotTimedOut());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_request_has_timed_out()
    {
        // Check the maximum amount of time allowed by amazon, by default this is set to 120 seconds so anything after that should fail
        $date = date('Y-m-d\TH:i:s\Z') - 150;
        $request = ['request' => ['timestamp' => $date]];
        $this->expectException(AlexaValidationException::class);
        $validator = new AlexaRequestValidator('foo', json_encode($request), 'https://s3.amazonaws.com/echo.api/echo-api-cert.pem', 'foo');
        $validator->requestHasNotTimedOut();
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
        $this->expectException(AlexaValidationException::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'http://s3.amazonaws.com/echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());

    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_signature_certificate_url_has_invalid_hostname()
    {
        // URLS taken from: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
        $this->expectException(AlexaValidationException::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'https://notamazon.com/echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());

    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_signature_certificate_url_has_non_lower_case_path()
    {
        // URLS taken from: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
        $this->expectException(AlexaValidationException::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com/EcHo.aPi/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_signature_certificate_url_has_invalid_path()
    {
        // URLS taken from: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
        $this->expectException(AlexaValidationException::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com/invalid.path/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_signature_certificate_url_has_invalid_port()
    {
        // URLS taken from: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
        $this->expectException(AlexaValidationException::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com:563/echo.api/echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_signature_certificate_url_has_invalid_port_and_requires_normalization()
    {
        // URLS taken from: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
        $this->expectException(AlexaValidationException::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com:563/echo.api/../echo-api-cert.pem', 'foo');
        $this->assertTrue($validator->isValidSignatureChainUrl());
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_pem_is_not_valid()
    {
        $this->expectException(AlexaValidationException::class);
        $validator = new AlexaRequestValidator('foo', 'foo', 'https://s3.amazonaws.com:563/echo.api/../echo-api-cert.pem', 'foo');
        $validator->hasValidSignatureChain('foo');
    }

    /**
     * @test
     */
    public function it_downloads_the_pem_file()
    {
        $validator = new AlexaRequestValidator('foo', 'foo', self::VALID_PEM_URL, 'foo');
        $pem = $validator->getPem();
        $this->assertNotEmpty($pem);
        $this->assertRegExp("/^-----BEGIN CERTIFICATE-----/", $pem);
    }

    /**
     * @test
     */
    public function it_returns_the_parsed_certificate()
    {
        $validator = new AlexaRequestValidator('foo', 'foo', self::VALID_PEM_URL, 'foo');
        $pem = $validator->getPem();
        $cert = $validator->getCertificate($pem);
        $this->assertArrayHasKey('validTo_time_t', $cert);
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_pem_cannot_be_parsed()
    {
        $this->expectException(AlexaValidationException::class);
        $validator = new AlexaRequestValidator('foo', 'foo', self::VALID_PEM_URL, 'foo');
        $validator->getCertificate('foo');

    }

    /**
     * @test
     */
    public function it_returns_true_when_the_certificate_has_not_expired()
    {
        $validator = new AlexaRequestValidator('foo', 'foo', self::VALID_PEM_URL, 'foo');
        $pem = $validator->getPem();
        $cert = $validator->getCertificate($pem);
        $this->assertTrue($validator->certificateHasNotExpired($cert));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_certificate_not_expired()
    {
        $this->expectException(AlexaValidationException::class);
        $validator = new AlexaRequestValidator('foo', 'foo', self::VALID_PEM_URL, 'foo');
        $pem = $validator->getPem();
        $cert = $validator->getCertificate($pem);
        $cert['validTo_time_t'] = time() - 100; // Set validTo timestamp in past
        $validator->certificateHasNotExpired($cert);
    }

    /**
     * @test
     */
    public function it_returns_true_when_the_certificate_has_valid_sana()
    {
        $validator = new AlexaRequestValidator('foo', 'foo', self::VALID_PEM_URL, 'foo');
        $pem = $validator->getPem();
        $cert = $validator->getCertificate($pem);
        $this->assertTrue($validator->certificateHasValidSans($cert));
    }

    /**
     * @test
     */
    public function it_throws_an_exception_when_the_certificate_not_have_a_valid_sans()
    {
        $this->expectException(AlexaValidationException::class);
        $validator = new AlexaRequestValidator('foo', 'foo', self::VALID_PEM_URL, 'foo');
        $pem = $validator->getPem();
        $cert = $validator->getCertificate($pem);
        $cert['extensions']['subjectAltName'] = 'foo.bar'; // Set invalid sans

        $validator->certificateHasValidSans($cert);
    }
}
