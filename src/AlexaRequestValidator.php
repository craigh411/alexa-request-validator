<?php


namespace Humps\AlexaRequest;

use Exception;
use Humps\AlexaRequest\Exceptions\AlexaValidationException;
use URL\Normalizer;

class AlexaRequestValidator
{
    private $rawRequest;
    private $requestBody;
    private $applicationId;
    private $signatureChainUrl;
    private $signature;
    private $timeout;


    /**
     * AmazonSkillRequestVerifier constructor.
     * @param $applicationId - The Id for the alexa application
     * @param $request - The amazon request (generally file_get_contents('php://input'))
     * @param $signatureChainUrl
     * @param $signature
     * @param int $timeout - The timeout tolerance for requests. Amazon allows no more that 150 seconds (defaults to 120)
     */
    function __construct($applicationId, $request, $signatureChainUrl, $signature, $timeout = 120)
    {
        $un = new Normalizer($signatureChainUrl);

        $this->rawRequest = $request;
        $this->requestBody = json_decode($request);
        $this->applicationId = $applicationId;
        $this->signatureChainUrl = $un->normalize();
        $this->signature = base64_decode($signature);
        $this->timeout = $timeout;
        // get port, we will need to check it is 443 if exists, but normalizer removes it.
        $this->port = parse_url($signatureChainUrl, PHP_URL_PORT);
    }

    /**
     * Returns true if all Alexa request validations pass
     * @return bool
     */
    public function validateRequest()
    {
        if ($this->isValidApplicationId()) {
            if ($this->requestHasNotTimedOut()) {
                if ($this->isValidSignatureChainUrl()) {
                    // Is a valid signatureChainUrl so we can download the PEM-encoded x.509 cert
                    $pem = $this->getPem();
                    $cert = $this->getCertificate($pem);
                    if ($this->hasValidSignatureChain($pem)) {
                        if ($this->certificateHasNotExpired($cert)) {
                            if ($this->certificateHasValidSans($cert)) {
                                return $this->hashesMatch($pem);
                            }
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Returns true if the request applicationId matches the given applicationId
     * @return bool
     * @throws AlexaValidationException
     */
    public function isValidApplicationId()
    {
        if ($this->applicationId === $this->requestBody->session->application->applicationId) {
            return true;
        }
        throw new AlexaValidationException('Invalid Application Id. Request came from an unknown application.');
    }

    /**
     * Returns true if the decrypted signature matches the sha1 hash of the request body
     * @param $pem
     * @return bool
     * @throws AlexaValidationException
     */
    public function hashesMatch($pem)
    {
        if ($publicKey = openssl_pkey_get_public($pem)) {
            openssl_public_decrypt($this->signature, $decryptedSignature, $publicKey);
            $responseHash = sha1($this->rawRequest);

            $decryptedSignature = bin2hex($decryptedSignature);

            if (substr($decryptedSignature, 30) === $responseHash) {
                return true;
            }
        } else {
            throw new AlexaValidationException('Unable to extract public key');
        }
        throw new AlexaValidationException('Invalid request: Hashes do not match');

    }


    /**
     * Returns true if the request has not timed out
     * @return bool
     * @throws AlexaValidationException
     */
    public function requestHasNotTimedOut()
    {
        if ((strtotime($this->requestBody->request->timestamp) + $this->timeout) > time()) {
            return true;
        }

        throw new AlexaValidationException('Request timeout');
    }

    /**
     * Returns true if the certificate has a valid SANS (subject alternative name)
     * @param $cert
     * @return bool
     * @throws AlexaValidationException
     */
    public function certificateHasValidSans($cert)
    {
        if (stristr($cert['extensions']['subjectAltName'], 'echo-api.amazon.com')) {
            return true;
        }

        throw new AlexaValidationException('Invalid Sans Check');
    }

    /**
     * Returns true if the certificate has not expired
     * @param $cert
     * @return bool
     * @throws AlexaValidationException
     */
    public function certificateHasNotExpired($cert)
    {
        if ($cert['validTo_time_t'] > time()) {
            return true;
        }

        throw new AlexaValidationException('Certificate no longer valid');
    }

    /**
     * Returns the parsed x509 certificate
     * @param $pem
     * @return array
     * @throws AlexaValidationException
     */
    public function getCertificate($pem)
    {
        $cert = openssl_x509_parse($pem);
        if (!empty($cert)) {
            return $cert;
        }

        throw new AlexaValidationException('Invalid PEM');
    }


    /**
     * Returns true is the SSL chain origin can be verified
     * @param $pem
     * @return bool
     * @throws AlexaValidationException
     */
    public function hasValidSignatureChain($pem)
    {
        try{
            if (openssl_verify($this->rawRequest, $this->signature, $pem, 'sha1') === 1) {
                return true;
            }
        }catch(Exception $e){
            throw new AlexaValidationException($e->getMessage());
        }


        throw new AlexaValidationException('Unknown SSL Chain Origin');
    }


    /**
     * Returns the given PEM file
     * @return bool|string
     */
    public function getPem()
    {
        $pemHashFile = sys_get_temp_dir() . '/' . hash("sha256", $this->signatureChainUrl) . ".pem";
        if (!file_exists($pemHashFile)) {
            file_put_contents($pemHashFile, file_get_contents($this->signatureChainUrl));
        }

        return file_get_contents($pemHashFile);
    }

    /**
     * Returns true if the signature chain URL is a valid Amazon URL.
     * See: https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/developing-an-alexa-skill-as-a-web-service#h2_verify_sig_cert
     * @return bool
     * @throws AlexaValidationException
     */
    public function isValidSignatureChainUrl()
    {
        $url = parse_url($this->signatureChainUrl);
        if(is_null($this->port) || $this->port === 443) {
            if (strcasecmp($url['scheme'], 'https') == 0 && strcasecmp($url['host'], 's3.amazonaws.com') == 0) {
                $path = explode('/', $url['path']);
                if ($path[1] === 'echo.api') {
                    return true;
                }
            }
        }

        throw new AlexaValidationException('Invalid Signature Chain Url');

    }
}