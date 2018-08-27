<?php


class ilUserCertificateRepository
{
	/**
	 * @var ilDB
	 */
	private $database;

	/**
	 * @var ilLogger
	 */
	private $logger;

	/**
	 * @param ilDBInterface $database
	 * @param ilLogger $logger
	 */
	public function __construct(\ilDBInterface $database, ilLogger $logger)
	{
		$this->database = $database;
		$this->logger = $logger;
	}

	/**
	 * @param ilUserCertificate $userCertificate
	 * @throws ilDatabaseException
	 */
	public function save(ilUserCertificate $userCertificate)
	{
		$this->logger->debug('Start saving of user certificate');

		$version = $this->fetchLatestVersion($userCertificate->getObjId(), $userCertificate->getUserId());
		$version += 1;

		$id = $this->database->nextId('user_certificates');

		$objId = $userCertificate->getObjId();
		$userId = $userCertificate->getUserId();

		$this->deactivatePreviousCertificates($objId, $userId);

		$columns = array(
			'id'                     => array('integer', $id),
			'pattern_certificate_id' => array('integer', $userCertificate->getPatternCertificateId()),
			'obj_id'                 => array('integer', $objId),
			'obj_type'               => array('clob', $userCertificate->getObjType()),
			'user_id'                => array('integer', $userId),
			'user_name'              => array('string', $userCertificate->getUserName()),
			'acquired_timestamp'     => array('clob', $userCertificate->getAcquiredTimestamp()),
			'certificate_content'    => array('clob', $userCertificate->getCertificateContent()),
			'template_values'        => array('clob', $userCertificate->getTemplateValues()),
			'valid_until'            => array('integer', $userCertificate->getValidUntil()),
			'version'                => array('text', $version),
			'ilias_version'          => array('text', $userCertificate->getIliasVersion()),
			'currently_active'       => array('integer', (integer)$userCertificate->isCurrentlyActive()),
			'background_image_path'  => array('clob', $userCertificate->getBackgroundImagePath()),
		);

		$this->logger->debug(sprintf('Save certificate with following values: %s', json_encode($columns, JSON_PRETTY_PRINT)));

		$this->database->insert('user_certificates', $columns);
	}

	/**
	 * @param $userId
	 * @return array
	 */
	public function fetchActiveCertificates($userId)
	{
		$sql = 'SELECT * FROM user_certificates WHERE user_id = ' . $this->database->quote($userId, 'integer') . ' AND currently_active = 1';

		$query = $this->database->query($sql);

		$result = array();
		while ($row = $this->database->fetchAssoc($query)) {
			$result[] = new ilUserCertificate(
				$row['pattern_certificate_id'],
				$row['obj_id'],
				$row['obj_type'],
				$row['user_id'],
				$row['user_name'],
				$row['acquired_timestamp'],
				$row['certificate_content'],
				$row['template_values'],
				$row['valid_until'],
				$row['version'],
				$row['ilias_version'],
				$row['currently_active'],
				$row['background_image_path'],
				$row['id']
			);
		}

		return $result;
	}

	public function fetchActiveCertificate($userId, $objectId)
	{
		$sql = 'SELECT *
FROM user_certificates
WHERE user_id = ' . $this->database->quote($userId, 'integer') . '
AND obj_id = ' . $this->database->quote($objectId, 'integer') . '
AND currently_active = 1';

		$query = $this->database->query($sql);

		while ($row = $this->database->fetchAssoc($query)) {
			return new ilUserCertificate(
				$row['pattern_certificate_id'],
				$row['obj_id'],
				$row['obj_type'],
				$row['user_id'],
				$row['user_name'],
				$row['acquired_timestamp'],
				$row['certificate_content'],
				$row['template_values'],
				$row['valid_until'],
				$row['version'],
				$row['ilias_version'],
				$row['currently_active'],
				$row['background_image_path'],
				$row['id']
			);
		}

		throw new ilException(sprintf('There is no active entry for user id: "%s" and object id: "%s"', $userId, $objectId));
	}

	/**
	 * @param $userId
	 * @param $objectIds
	 * @return array
	 */
	public function fetchActiveCertificateForObjectIds($userId, $objectIds)
	{
		$sql = 'SELECT *
FROM user_certificates
WHERE user_id = ' . $this->database->quote($userId, 'integer') . '
 AND obj_id IN (' . implode(',', $objectIds) . ')
 AND currently_active = 1';

		$query = $this->database->query($sql);

		$result = array();
		while ($row = $this->database->fetchAssoc($query)) {
			$result[] = new ilUserCertificate(
				$row['pattern_certificate_id'],
				$row['obj_id'],
				$row['obj_type'],
				$row['user_id'],
				$row['user_name'],
				$row['acquired_timestamp'],
				$row['certificate_content'],
				$row['template_values'],
				$row['valid_until'],
				$row['version'],
				$row['ilias_version'],
				$row['currently_active'],
				$row['background_image_path'],
				$row['id']
			);
		}

		return $result;
	}

	/**
	 * @param $id
	 * @return ilUserCertificate
	 * @throws ilException
	 */
	public function fetchCertificate($id)
	{
		$sql = 'SELECT * FROM user_certificates WHERE id = ' . $this->database->quote($id, 'integer');

		$query = $this->database->query($sql);

		while ($row = $this->database->fetchAssoc($query)) {
			return new ilUserCertificate(
				$row['pattern_certificate_id'],
				$row['obj_id'],
				$row['obj_type'],
				$row['user_id'],
				$row['user_name'],
				$row['acquired_timestamp'],
				$row['certificate_content'],
				$row['template_values'],
				$row['valid_until'],
				$row['version'],
				$row['ilias_version'],
				$row['currently_active'],
				$row['background_image_path'],
				$row['id']
			);
		}

		throw new ilException('No certificate found for user certificate id: ' . $id);
	}

	/**
	 * @param $objId
	 * @param $userId
	 * @return array
	 */
	private function fetchCertificatesOfObject($objId, $userId)
	{
		$this->logger->debug(sprintf(
			'Start fetching all certificates(user id: "%s", object id: "%s")',
			$userId,
			$objId
		));

		$sql = 'SELECT * FROM user_certificates 
WHERE user_id = ' . $userId . '
AND obj_id = ' . $objId;

		$query = $this->database->query($sql);

		$result = array();
		while ($row = $this->database->fetchAssoc($query)) {
			$this->logger->debug(sprintf(
				'Certificate found: "%s")',
				json_encode($row,JSON_PRETTY_PRINT)
			));

			$result[] = new ilUserCertificate(
				$row['pattern_certificate_id'],
				$row['obj_id'],
				$row['obj_type'],
				$row['user_id'],
				$row['user_name'],
				$row['acquired_timestamp'],
				$row['certificate_content'],
				$row['template_values'],
				$row['valid_until'],
				$row['version'],
				$row['ilias_version'],
				$row['currently_active'],
				$row['background_image_path'],
				$row['id']
			);
		}

		$this->logger->debug('End of fetching certificates of object ');

		return $result;
	}

	private function fetchLatestVersion($objId, $userId)
	{
		$this->logger->debug('Start of fetching latest version');

		$templates = $this->fetchCertificatesOfObject($objId, $userId);

		$version = 0;
		foreach ($templates as $template) {
			if ($template->getVersion() > $version) {
				$version = $template->getVersion();
			}
		}

		$this->logger->debug(sprintf('Latest version: %s' , $version));

		return $version;
	}

	/**
	 * @param $objId
	 * @param $userId
	 * @throws ilDatabaseException
	 */
	private function deactivatePreviousCertificates($objId, $userId)
	{
		$this->logger->debug(sprintf('Start deactivating previous certificates for user id: "%s" and object id: "%s"', $userId, $objId));

		$sql = '
UPDATE user_certificates
SET currently_active = 0
WHERE obj_id = ' . $this->database->quote($objId, 'integer') . '
AND  user_id = ' . $this->database->quote($userId, 'integer');

		$query = $this->database->query($sql);
		$this->database->execute($query);

		$this->logger->debug('End of deactivating');
	}
}
