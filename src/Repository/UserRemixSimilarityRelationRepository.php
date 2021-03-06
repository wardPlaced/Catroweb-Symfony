<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\ORM\EntityRepository;


/**
 * Class UserRemixSimilarityRelationRepository
 * @package App\Repository
 */
class UserRemixSimilarityRelationRepository extends EntityRepository
{
  /**
   *
   */
  public function removeAllUserRelations()
  {
    $qb = $this->createQueryBuilder('ur');

    $qb
      ->delete()
      ->getQuery()
      ->execute();
  }

  /**
   * @param User $user
   *
   * @return mixed
   */
  public function getRelationsOfSimilarUsers(User $user)
  {
    $qb = $this->createQueryBuilder('ur');

    return $qb
      ->select('ur')
      ->where($qb->expr()->eq('ur.first_user', ':user'))
      ->orWhere($qb->expr()->eq('ur.second_user', ':user'))
      ->orderBy('ur.similarity', 'DESC')
      ->setParameter('user', $user)
      ->distinct()
      ->getQuery()
      ->getResult();
  }

  /**
   * @param $first_user_id
   * @param $second_user_id
   * @param $similarity
   *
   * @throws \Doctrine\DBAL\DBALException
   * @throws \Exception
   */
  public function insertRelation($first_user_id, $second_user_id, $similarity)
  {
    $connection = $this->getEntityManager()->getConnection();
    $connection->insert('user_remix_similarity_relation', [
      'first_user_id'  => $first_user_id,
      'second_user_id' => $second_user_id,
      'similarity'     => $similarity,
      'created_at'     => date_format(new \DateTime(), "Y-m-d H:i:s"),
    ]);
  }
}
