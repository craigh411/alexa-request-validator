<?php


namespace Humps\AlexaRequest;


use Exception;
use URL\Normalizer;

class AlexaRequestValidator
{
    private $rawRequest;
    private $requestBody;
    private $remoteAddress;
    private $applicationId;
    private $signatureChainUrl;
    private $signature;
    private $timeout;


    /**
     * AmazonSkillRequestVerifier constructor.
     * @param $applicationId - The Id for the alexa application
     * @param $request - The amazon request (generally file_get_contents('php://input'))
     * @param $remoteAddress -
     * @param $signatureChainUrl
     * @param $signature
     * @param int $timeout - The timeout tolerance for requests. Amazon allows no more that 150 seconds (defaults to 120)
     */
    function __construct($applicationId, $request, $remoteAddress, $signatureChainUrl, $signature, $timeout = 120)
    {
        $un =  new Normalizer($signatureChainUrl);

        $this->rawRequest = $request;
        $this->requestBody = json_decode($request);
        $this->remoteAddress = $remoteAddress;
        $this->applicationId = $applicationId;
        $this->signatureChainUrl = $un->normalize();
        $this->signature = base64_decode($signature);
        $this->timeout = $timeout;
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
     * @throws Exception
     */
    public function isValidApplicationId()
    {
        if ($this->applicationId === $this->requestBody->session->application->applicationId) {
            return true;
        }
        throw new Exception('Invalid Application Id. Request came from an unknown application.');
    }

    /**
     * Returns true if the decrypted signature matches the sha1 hash of the request body
     * @param $pem
     * @return bool
     * @throws Exception
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
            throw new Exception('Unable to extract public key');
        }
        throw new Exception('Invalid request: Hashes do not match');

    }


    /**
     * Returns true if the request has not timed out
     * @return bool
     * @throws Exception
     */
    public function requestHasNotTimedOut()
    {
        if ((strtotime($this->requestBody->request->timestamp) + $this->timeout) > time()) {
            return true;
        }

        throw new Exception('Request timeout');
    }

    /**
     * Returns true if the certificate has a valid SANS (subject alternative name)
     * @param $cert
     * @return bool
     * @throws Exception
     */
    public function certificateHasValidSans($cert)
    {
        if (stristr($cert['extensions']['subjectAltName'], 'echo-api.amazon.com')) {
            return true;
        }

        throw new Exception('Invalid Sans Check');
    }

    /**
     * Returns true if the certificate has not expired
     * @param $cert
     * @return bool
     * @throws Exception
     */
    public function certificateHasNotExpired($cert)
    {
        if ($cert['validTo_time_t'] > time()) {
            return true;
        }

        throw new Exception('Certificate no longer valid');
    }

    /**
     * Returns the parsed x509 certificate
     * @param $pem
     * @return array
     * @throws Exception
     */
    public function getCertificate($pem)
    {
        $cert = openssl_x509_parse($pem);
        if (!empty($cert)) {
            return $cert;
        }

        throw new Exception('Invalid PEM');
    }


    /**
     * Returns true is the SSL chain origin can be verified
     * @param $pem
     * @return bool
     * @throws Exception
     */
    public function hasValidSignatureChain($pem)
    {
        if (openssl_verify($this->rawRequest, $this->signature, $pem, 'sha1') === 1) {
            return true;
        }

        throw new Exception('Unknown SSL Chain Origin');
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
     * @return bool
     * @throws Exception
     */
    public function isValidSignatureChainUrl()
    {
        if (preg_match("/https:\/\/s3.amazonaws.com(\:443)?\/echo.api\/*/i", $this->signatureChainUrl) !== false) {
            return true;
        }

        throw new Exception('Invalid Signature Chain Url');
    }
}