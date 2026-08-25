<?php

declare(strict_types=1);

namespace OCA\ERP\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @extends QBMapper<CompanyProfile>
 */
class CompanyProfileMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'erp_company_profile', CompanyProfile::class);
	}

	/**
	 * Singleton — es wird nie mehr als eine Zeile angelegt (siehe
	 * CompanyProfileService::update()), daher ohne WHERE: null, solange
	 * noch nie gespeichert wurde.
	 */
	public function find(): ?CompanyProfile {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
