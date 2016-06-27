<?php

namespace BirdSystem\Utility;

use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use BirdSystem\Controller\Exception\AppException;
use BirdSystem\Db\TableGateway\CompanyConfig;
use BirdSystem\Traits\AuthenticationTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class Uploader implements LoggerAwareInterface
{
    use ServiceLocatorAwareTrait, AuthenticationTrait, LoggerAwareTrait;

    protected $_enableS3Upload = true;
    protected $_remoteBucket;
    protected $_amazonEndpoint = 'http://s3.birdsystem.co.uk';
    /**
     * @var S3Client $s3Client
     */
    protected $s3Client = null;

    protected $_fileName;

    protected $_path = null;

    public function upload($localFilePath, $remoteFileName = null)
    {

        if ($remoteFileName == '') {
            $fileInfo      = pathinfo($localFilePath);
            $localFilename = $fileInfo['basename'];

            $this->_fileName = $localFilename;
        } else {
            $this->_fileName = $remoteFileName;
        }

        if ($this->_enableS3Upload === true) {
            $credentials = $this->s3Client->getCredentials();
            $result      = $credentials->wait();
            if (is_null($result) || is_null($result->getAccessKeyId()) || is_null($result->getSecretKey())) {
                throw new AppException('Please set the amazon s3 credential.');
            }
        }

        // do s3 upload

        $url = $this->amazonS3Upload($localFilePath);

        return $url;
    }

    public function delete($remoteFilePath)
    {
        return $status = $this->amazonS3Delete($remoteFilePath);
    }

    public function setUploadCredential($companyId = 0)
    {
        if ($companyId == 0) {
            $UserInfo  = $this->getUserInfo();
            $companyId = $UserInfo->getCompanyId();
        }
        $CompanyConfigTG = $this->serviceLocator->get(CompanyConfig::class);
        $companyConfig   = $CompanyConfigTG->getByCompanyId($companyId);

        $this->_remoteBucket = substr(strtolower($companyConfig['amazon-remote-path']), 0, -1);

//        $this->_path = 'http://' . _amazonEndpoint . '/' . $this->_remoteUploadFilePath;
        $this->_path    = 'http://' . $this->_remoteBucket . '.s3.amazonaws.com/';
        $this->s3Client = new S3Client([
            'endpoint'        => $this->_path,
            'bucket_endpoint' => true,
            'version'         => 'latest',
            'region'          => 'ap-northeast-1',
            'credentials'     => [
                'key'    => $companyConfig['amazon-s3-access-key-id'],
                'secret' => $companyConfig['amazon-secret-access-key'],
            ],
        ]);

    }

    public function __get($property)
    {
        return $this->$property;
    }

    private function amazonS3Upload($localFilePath)
    {
        try {
            $result = $this->s3Client->putObject([
                'Bucket'     => $this->_remoteBucket,
                'Key'        => $this->_fileName,
                'SourceFile' => $localFilePath,
            ]);
        } catch (S3Exception $e) {
            // Catch an S3 specific exception.
            throw new AppException($e->getMessage());
        } catch (AwsException $e) {
            // This catches the more generic AwsException. You can grab information
            // from the exception using methods of the exception object.
            throw new AppException($e->getAwsRequestId() . "\n" .
                                   $e->getAwsErrorType() . "\n" .
                                   $e->getAwsErrorCode() . "\n");
        }

//        $url = $this->_amazonEndpoint . '/' . $this->_remoteBucket . $this->_fileName;

        return $result['ObjectURL'];
    }

    private function amazonS3Delete($remoteFilePath)
    {
        return $this->s3Client->deleteObject([
            'Bucket' => $this->_remoteBucket,
            'Key'    => $remoteFilePath,
        ]);
    }

    public function getPath()
    {
        return $this->_path;
    }
}
