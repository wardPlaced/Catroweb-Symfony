<?php

namespace App\Repository;

use App\Entity\Program;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;


/**
 * Class ProgramRepository
 * @package App\Repository
 */
class ProgramRepository extends EntityRepository
{
  /**
   * @var array
   */
  private $cached_most_remixed_programs_full_result = [];
  /**
   * @var array
   */
  private $cached_most_liked_programs_full_result = [];
  /**
   * @var array
   */
  private $cached_most_downloaded_other_programs_full_result = [];

  /**
   * @param null $flavor
   * @param int  $limit
   * @param int  $offset
   * @param int  $max_version
   *
   * @return mixed
   */
  public function getMostDownloadedPrograms($flavor = null, $limit = 20, $offset = 0, $max_version = 0)
  {
    $qb = $this->createQueryBuilder('e');

    $qb->select('e')
      ->where($qb->expr()->eq('e.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('e.private', $qb->expr()->literal(false)))
      ->orderBy('e.downloads', 'DESC')
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    if ($flavor)
    {
      $qb
        ->andWhere($qb->expr()->eq('e.flavor', ':flavor'))
        ->setParameter('flavor', $flavor);
    }

    if ($max_version !== 0)
    {
      $qb
        ->andWhere($qb
          ->expr()->lte('e.language_version', ':max_version'))
        ->setParameter('max_version', $max_version);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * @param null $flavor
   * @param int  $limit
   * @param int  $offset
   * @param int  $max_version
   *
   * @return mixed
   */
  public function getMostViewedPrograms($flavor = null, $limit = 20, $offset = 0, $max_version = 0)
  {
    $qb = $this->createQueryBuilder('e');

    $qb
      ->select('e')
      ->where($qb->expr()->eq('e.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('e.private', $qb->expr()->literal(false)))
      ->orderBy('e.views', 'DESC')
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    if ($flavor)
    {
      $qb
        ->andWhere($qb->expr()->eq('e.flavor', ':flavor'))
        ->setParameter('flavor', $flavor);
    }

    if ($max_version !== 0)
    {
      $qb
        ->andWhere($qb
          ->expr()->lte('e.language_version', ':max_version'))
        ->setParameter('max_version', $max_version);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * @param $limit
   * @param $offset
   *
   * @return string
   */
  private function generateUnionSqlStatementForMostRemixedPrograms($limit, $offset)
  {
    //--------------------------------------------------------------------------------------------------------------
    // ATTENTION: since Doctrine does not support UNION queries, the following query is a native MySQL/SQLite query
    //--------------------------------------------------------------------------------------------------------------
    $limit_clause = intval($limit) > 0 ? 'LIMIT ' . intval($limit) : '';
    $offset_clause = intval($offset) > 0 ? 'OFFSET ' . intval($offset) : '';

    return "
            SELECT sum(remixes_count) AS total_remixes_count, id FROM (
                    SELECT p.id AS id, COUNT(p.id) AS remixes_count
                    FROM program p
                    INNER JOIN program_remix_relation r
                    ON p.id = r.ancestor_id
                    INNER JOIN program rp
                    ON r.descendant_id = rp.id
                    WHERE p.visible = 1
                    AND p.flavor = :flavor
                    AND p.private = 0
                    AND r.depth = 1
                    AND p.user_id <> rp.user_id
                    GROUP BY p.id
                UNION ALL
                    SELECT p.id AS id, COUNT(p.id) AS remixes_count
                    FROM program p
                    INNER JOIN program_remix_backward_relation b
                    ON p.id = b.parent_id
                    INNER JOIN program rp
                    ON b.child_id = rp.id
                    WHERE p.visible = 1
                    AND p.flavor = :flavor
                    AND p.private = 0
                    AND p.user_id <> rp.user_id
                    GROUP BY p.id
            ) t
            GROUP BY id
            ORDER BY remixes_count DESC
            $limit_clause
            $offset_clause
        ";
  }


  /**
   * @param string $flavor
   * @param int    $limit
   * @param int    $offset
   *
   * @return array
   * @throws \Doctrine\DBAL\DBALException
   */
  public function getMostRemixedPrograms($flavor = 'pocketcode', $limit = 20, $offset = 0)
  {
    if (!isset($this->cached_most_remixed_programs_full_result[$flavor]))
    {
      $connection = $this->getEntityManager()->getConnection();
      $statement = $connection->prepare($this->generateUnionSqlStatementForMostRemixedPrograms($limit, $offset));
      $statement->bindValue('flavor', $flavor);
      $statement->execute();
      $results = $statement->fetchAll();
    }
    else
    {
      $results = array_slice($this->cached_most_remixed_programs_full_result[$flavor], $offset, $limit);
    }

    $programs = [];
    foreach ($results as $result)
    {
      $programs[] = $this->find($result['id']);
    }

    return $programs;
  }

  /**
   * @param string $flavor
   *
   * @return int
   * @throws \Doctrine\DBAL\DBALException
   */
  public function getTotalRemixedProgramsCount($flavor = 'pocketcode')
  {
    if (isset($this->cached_most_remixed_programs_full_result[$flavor]))
    {
      return count($this->cached_most_remixed_programs_full_result[$flavor]);
    }

    $connection = $this->getEntityManager()->getConnection();
    $statement = $connection->prepare($this->generateUnionSqlStatementForMostRemixedPrograms(0, 0));
    $statement->bindValue('flavor', $flavor);
    $statement->execute();

    $this->cached_most_remixed_programs_full_result[$flavor] = $statement->fetchAll();

    return count($this->cached_most_remixed_programs_full_result[$flavor]);
  }

  /**
   * @param string $flavor
   * @param int    $limit
   * @param int    $offset
   *
   * @return array
   */
  public function getMostLikedPrograms($flavor = 'pocketcode', $limit = 20, $offset = 0)
  {
    if (isset($this->cached_most_liked_programs_full_result[$flavor]))
    {
      return array_slice($this->cached_most_liked_programs_full_result[$flavor], $offset, $limit);
    }

    $qb = $this->createQueryBuilder('e');

    $qb
      ->select(['e as program', 'COUNT(e.id) as like_count'])
      ->innerJoin('App\Entity\ProgramLike', 'l', Join::WITH, $qb->expr()->eq('e.id', 'l.program_id'))
      ->where($qb->expr()->eq('e.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('e.flavor', ':flavor'))
      ->andWhere($qb->expr()->eq('e.private', $qb->expr()->literal(false)))
      ->having($qb->expr()->gt('like_count', $qb->expr()->literal(1)))
      ->groupBy('e.id')
      ->orderBy('like_count', 'DESC')
      ->setParameter('flavor', $flavor)
      ->distinct();

    if (intval($offset) > 0)
    {
      $qb->setFirstResult($offset);
    }

    if (intval($limit) > 0)
    {
      $qb->setMaxResults($limit);
    }

    $results = $qb->getQuery()->getResult();

    return array_map(function ($r) {
      return $r['program'];
    }, $results);
  }

  /**
   * @param string $flavor
   *
   * @return int
   */
  public function getTotalLikedProgramsCount($flavor = 'pocketcode')
  {
    if (isset($this->cached_most_liked_programs_full_result[$flavor]))
    {
      return count($this->cached_most_liked_programs_full_result[$flavor]);
    }

    $this->cached_most_liked_programs_full_result[$flavor] = $this->getMostLikedPrograms($flavor, 0, 0);

    return count($this->cached_most_liked_programs_full_result[$flavor]);
  }

  /**
   * @param $flavor string
   * @param $program Program
   * @param $limit
   * @param $offset
   * @param $is_test_environment
   *
   * @return array
   */
  public function getOtherMostDownloadedProgramsOfUsersThatAlsoDownloadedGivenProgram($flavor, $program, $limit, $offset, $is_test_environment)
  {
    $cache_key = $flavor . '_' . $program->getId();
    if (isset($this->cached_most_downloaded_other_programs_full_result[$cache_key]))
    {
      return array_slice($this->cached_most_downloaded_other_programs_full_result[$cache_key], $offset, $limit);
    }

    $time_frame_length = 600; // in seconds
    $qb = $this->createQueryBuilder('e');

    $qb
      ->select(['e as program', 'COUNT(e.id) as user_download_count'])
      ->innerJoin('App\Entity\ProgramDownloads', 'd1', Join::WITH, $qb->expr()->eq('e.id', 'd1.program'))
      ->innerJoin('App\Entity\ProgramDownloads', 'd2', Join::WITH, $qb->expr()->eq('d1.user', 'd2.user'))
      ->where($qb->expr()->eq('e.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('e.flavor', ':flavor'))
      ->andWhere($qb->expr()->eq('e.private', $qb->expr()->literal(false)))
      ->andWhere($qb->expr()->isNotNull('d1.user'))
      ->andWhere($qb->expr()->neq('d1.user', ':user'))
      ->andWhere($qb->expr()->neq('d1.program', 'd2.program'))
      ->andWhere($qb->expr()->eq('d2.program', ':program'));

    if (!$is_test_environment)
    {
      $qb->andWhere($qb->expr()->between('TIME_DIFF(d1.downloaded_at, d2.downloaded_at, \'second\')',
        $qb->expr()->literal($time_frame_length / 2 * (-1)), $qb->expr()->literal($time_frame_length / 2)));
    }

    $qb
      ->groupBy('e.id')
      ->orderBy('user_download_count', 'DESC')
      ->setParameter('flavor', $flavor)
      ->setParameter('user', $program->getUser())
      ->setParameter('program', $program)
      ->distinct();

    if (intval($offset) > 0)
    {
      $qb->setFirstResult($offset);
    }

    if (intval($limit) > 0)
    {
      $qb->setMaxResults($limit);
    }

    $results = $qb->getQuery()->getResult();

    return array_map(function ($r) {
      return $r['program'];
    }, $results);
  }

  /**
   * @param $flavor string
   * @param $program Program
   * @param $is_test_environment
   *
   * @return int
   */
  public function getOtherMostDownloadedProgramsOfUsersThatAlsoDownloadedGivenProgramCount($flavor, $program, $is_test_environment)
  {
    $cache_key = $flavor . '_' . $program->getId();
    if (isset($this->cached_most_downloaded_other_programs_full_result[$cache_key]))
    {
      return count($this->cached_most_downloaded_other_programs_full_result[$cache_key]);
    }

    $result = $this->getOtherMostDownloadedProgramsOfUsersThatAlsoDownloadedGivenProgram($flavor, $program, 0, 0, $is_test_environment);
    $this->cached_most_downloaded_other_programs_full_result[$cache_key] = $result;

    return count($this->cached_most_downloaded_other_programs_full_result[$cache_key]);
  }

  /**
   * @param null $flavor
   * @param int  $limit
   * @param int  $offset
   * @param int  $max_version
   *
   * @return mixed
   */
  public function getRecentPrograms($flavor = null, $limit = 20, $offset = 0, $max_version = 0)
  {
    $qb = $this->createQueryBuilder('e');

    $qb
      ->select('e')
      ->where($qb->expr()->eq('e.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('e.private', $qb->expr()->literal(false)))
      ->orderBy('e.uploaded_at', 'DESC')
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    if ($flavor)
    {
      $qb
        ->andWhere($qb->expr()->eq('e.flavor', ':flavor'))
        ->setParameter('flavor', $flavor);
    }

    if ($max_version !== 0)
    {
      $qb
        ->andWhere($qb
          ->expr()->lte('e.language_version', ':max_version'))
        ->setParameter('max_version', $max_version);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * @param null $flavor
   * @param int  $limit
   * @param int  $offset
   * @param int  $max_version
   *
   * @return array
   */
  public function getRandomPrograms($flavor = null, $limit = 20, $offset = 0, $max_version = 0)
  {
    // Rand(), newid() and TABLESAMPLE() doesn't exist in the Native Query therefore we have to do a workaround
    // for random results
    if ($offset > 0 && isset($_SESSION['randomProgramIds']))
    {
      $array_program_ids = $_SESSION['randomProgramIds'];
    }
    else
    {
      $array_program_ids = $this->getVisibleProgramIds($flavor, $max_version);
      shuffle($array_program_ids);
      $_SESSION['randomProgramIds'] = $array_program_ids;
    }

    $array_programs = [];
    $max_element = ($offset + $limit) > count($array_program_ids) ? count($array_program_ids) : $offset + $limit;
    $current_element = $offset;

    while ($current_element < $max_element)
    {
      $array_programs[] = $this->find($array_program_ids[$current_element]);
      $current_element++;
    }

    return $array_programs;
  }

  /**
   * @param     $flavor
   * @param int $max_version
   *
   * @return mixed
   */
  public function getVisibleProgramIds($flavor, $max_version = 0)
  {
    $qb = $this->createQueryBuilder('e');

    $qb
      ->select('e.id')
      ->where($qb->expr()->eq('e.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('e.private', $qb->expr()->literal(false)));

    if ($flavor)
    {
      $qb
        ->andWhere($qb->expr()->eq('e.flavor', ':flavor'))
        ->setParameter('flavor', $flavor);
    }

    if ($max_version !== 0)
    {
      $qb
        ->andWhere($qb
          ->expr()->lte('e.language_version', ':max_version'))
        ->setParameter('max_version', $max_version);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * @param int[] $program_ids
   *
   * @return int[]
   */
  public function filterExistingProgramIds(array $program_ids)
  {
    $qb = $this->createQueryBuilder('p');

    $result = $qb
      ->select(['p.id'])
      ->where('p.id IN (:program_ids)')
      ->setParameter('program_ids', $program_ids)
      ->distinct()
      ->getQuery()
      ->getResult();

    return array_map(function ($data) {
      return $data['id'];
    }, $result);
  }

  /**
   * @param $search_terms
   * @param $metadata
   *
   * @return string
   */
  public function getAppendableSqlStringForEveryTerm($search_terms, $metadata)
  {
    $sql = '';
    $parameter_index = 0;
    $i = 1;
    $end = count($metadata);

    foreach ($search_terms as $search_term)
    {
      $parameter = ":st" . $parameter_index;
      $parameter_index++;
      $tag_string = '';
      foreach ($metadata as $language)
      {
        if ($i == $end)
        {
          $tag_string .= '(t.' . $language . ' LIKE ' . $parameter . ')';
        }
        else
        {
          $tag_string .= '(t.' . $language . ' LIKE ' . $parameter . ') OR ';
        }
        $i++;
      }
      $i = 1;


      $sql .= "OR 
          ((e.name LIKE " . $parameter . " OR
          f.username LIKE " . $parameter . " OR
          e.description LIKE " . $parameter . " OR
          x.name LIKE " . $parameter . " OR
          " . $tag_string . " OR
          e.id = " . $parameter . "int" . ") AND
          e.visible = true AND
          e.private = false) ";
    }

    return $sql;
  }

  /**
   * @param     $query
   * @param int $limit
   * @param int $offset
   * @param int $max_version
   *
   * @return array
   */
  public function search($query, $limit = 10, $offset = 0, $max_version = 0)
  {
    $em = $this->getEntityManager();
    $metadata = $em->getClassMetadata('App\Entity\Tag')->getFieldNames();
    array_shift($metadata);

    $query_addition_for_tags = '';
    $i = 1;
    $end = count($metadata);

    foreach ($metadata as $language)
    {
      if ($i == $end)
      {
        $query_addition_for_tags .= '(t.' . $language . ' LIKE :searchterm)';
      }
      else
      {
        $query_addition_for_tags .= '(t.' . $language . ' LIKE :searchterm) OR ';
      }
      $i++;
    }

    $search_terms = explode(" ", $query);

    $appendable_sql_string = '';
    $more_than_one_search_term = false;

    if (count($search_terms) > 1)
    {

      $appendable_sql_string = $this->getAppendableSqlStringForEveryTerm($search_terms, $metadata);
      $more_than_one_search_term = true;
    }

    $dql = "SELECT e,
          (CASE
            WHEN (e.name LIKE :searchterm) THEN 10
            ELSE 0
          END) +
          (CASE
            WHEN (f.username LIKE :searchterm) THEN 1
            ELSE 0
          END) +
          (CASE
            WHEN (x.name LIKE :searchterm) THEN 7
            ELSE 0
          END) +
          (CASE
            WHEN (e.description LIKE :searchterm) THEN 3
            ELSE 0
          END) +
          (CASE
            WHEN (e.id = :searchtermint) THEN 11
            ELSE 0
          END) +
          (CASE
            WHEN ($query_addition_for_tags) THEN 7
            ELSE 0
          END)
          AS weight
        FROM App\Entity\Program e
        LEFT JOIN e.user f
        LEFT JOIN e.tags t
        LEFT JOIN e.extensions x
        WHERE
          ((e.name LIKE :searchterm OR
          f.username LIKE :searchterm OR
          e.description LIKE :searchterm OR
          x.name LIKE :searchterm OR
          $query_addition_for_tags OR
          e.id = :searchtermint) AND
          e.visible = true AND
          e.private = false) " . $appendable_sql_string;

    if ($max_version !== 0)
    {
      $dql .= " AND e.language_version <= " . $max_version;
    }

    $dql .= " ORDER BY weight DESC, e.uploaded_at DESC";

    $qb_program = $this->createQueryBuilder('e');
    $final_query = $qb_program->getEntityManager()->createQuery($dql);
    $final_query->setFirstResult($offset);
    $final_query->setMaxResults($limit);
    $final_query->setParameter('searchterm', '%' . $query . '%');
    $final_query->setParameter('searchtermint', intval($query));
    if ($more_than_one_search_term)
    {
      $parameter_index = 0;
      foreach ($search_terms as $search_term)
      {
        $parameter = ":st" . $parameter_index;
        $parameter_index++;
        $final_query->setParameter($parameter, '%' . $search_term . '%');
        $final_query->setParameter($parameter . 'int', intval($search_term));
      }
    }

    $paginator = new Paginator($final_query, $fetchJoinCollection = true);
    $result = $paginator->getIterator()->getArrayCopy();

    return array_map(function ($element) {
      return $element[0];
    }, $result);
  }

  /**
   * @param     $query
   * @param int $max_version
   *
   * @return int
   */
  public function searchCount($query, $max_version = 0)
  {
    $em = $this->getEntityManager();
    $metadata = $em->getClassMetadata('App\Entity\Tag')->getFieldNames();
    array_shift($metadata);

    $query_addition_for_tags = '';
    foreach ($metadata as $language)
    {
      $query_addition_for_tags .= 't.' . $language . ' LIKE :searchterm OR ';
    }

    $search_terms = explode(" ", $query);
    $appendable_sql_string = '';
    $more_than_one_search_term = false;
    if (count($search_terms) > 1)
    {
      $appendable_sql_string = $this->getAppendableSqlStringForEveryTerm($search_terms, $metadata);
      $more_than_one_search_term = true;
    }

    $qb_program = $this->createQueryBuilder('e');
    $dql = "SELECT e.id
        FROM App\Entity\Program e
        LEFT JOIN e.user f
        LEFT JOIN e.tags t
        LEFT JOIN e.extensions x
        WHERE
          ((e.name LIKE :searchterm OR
          f.username LIKE :searchterm OR
          e.description LIKE :searchterm OR
          x.name LIKE :searchterm OR
          $query_addition_for_tags
          e.id = :searchtermint) AND
          e.visible = true AND 
          e.private = false) " . $appendable_sql_string;

    if ($max_version !== 0)
    {
      $dql .= " AND e.language_version <= " . $max_version;
    }
    $dql .= " GROUP BY e.id";
    $q2 = $qb_program->getEntityManager()->createQuery($dql);
    $q2->setParameter('searchterm', '%' . $query . '%');
    $q2->setParameter('searchtermint', intval($query));
    if ($more_than_one_search_term)
    {
      $parameter_index = 0;
      foreach ($search_terms as $search_term)
      {
        $parameter = ":st" . $parameter_index;
        $parameter_index++;
        $q2->setParameter($parameter, '%' . $search_term . '%');
        $q2->setParameter($parameter . 'int', intval($search_term));
      }
    }
    $result = $q2->getResult();

    return count($result);
  }

  /**
   *
   */
  public function markAllProgramsAsNotYetMigrated()
  {
    $qb = $this->createQueryBuilder('p');

    $qb
      ->update()
      ->set('p.remix_migrated_at', ':remix_migrated_at')
      ->setParameter(':remix_migrated_at', null)
      ->getQuery()
      ->execute();
  }

  /**
   * @param $previous_program_id
   *
   * @return mixed
   * @throws \Doctrine\ORM\NonUniqueResultException
   */
  public function findNext($previous_program_id)
  {
    $qb = $this->createQueryBuilder('p');

    return $qb
      ->select('min(p.id)')
      ->where($qb->expr()->gt('p.id', ':previous_program_id'))
      ->setParameter('previous_program_id', $previous_program_id)
      ->distinct()
      ->getQuery()
      ->getSingleScalarResult();
  }

  /**
   * @param     $user_id
   * @param int $max_version
   *
   * @return mixed
   */
  public function getUserPrograms($user_id, $max_version = 0)
  {
    $qb = $this->createQueryBuilder('e');

    $qb
      ->select('e')
      ->leftJoin('e.user', 'f')
      ->where($qb->expr()->eq('e.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('f.id', ':user_id'))
      ->setParameter('user_id', $user_id);
    if ($max_version !== 0)
    {
      $qb
        ->andWhere($qb
          ->expr()->lte('e.language_version', ':max_version'))
        ->setParameter('max_version', $max_version);
    }

    return $qb->getQuery()->getResult();
  }

  /**
   * @param null $flavor
   * @param int  $max_version
   *
   * @return mixed
   * @throws \Doctrine\ORM\NonUniqueResultException
   */
  public function getTotalPrograms($flavor = null, $max_version = 0)
  {
    $qb = $this->createQueryBuilder('e');

    $qb
      ->select('COUNT (e.id)')
      ->where($qb->expr()->eq('e.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('e.private', $qb->expr()->literal(false)));

    if ($flavor)
    {
      $qb
        ->andWhere($qb->expr()->eq('e.flavor', ':flavor'))
        ->setParameter('flavor', $flavor);
    }

    if ($max_version !== 0)
    {
      $qb
        ->andWhere($qb
          ->expr()->lte('e.language_version', ':max_version'))
        ->setParameter('max_version', $max_version);
    }

    return $qb->getQuery()->getSingleScalarResult();
  }

  /**
   * @param $apk_status
   *
   * @return mixed
   */
  public function getProgramsWithApkStatus($apk_status)
  {
    $qb = $this->createQueryBuilder('e');

    return $qb
      ->select('e')
      ->where($qb->expr()->eq('e.apk_status', ':apk_status'))
      ->setParameter('apk_status', $apk_status)
      ->getQuery()
      ->getResult();
  }

  /**
   * @return mixed
   */
  public function getProgramsWithExtractedDirectoryHash()
  {
    $qb = $this->createQueryBuilder('e');

    return $qb
      ->select('e')
      ->where($qb->expr()->isNotNull('e.directory_hash'))
      ->getQuery()
      ->getResult();
  }

  /**
   * @param     $id
   * @param int $limit
   * @param int $offset
   *
   * @return mixed
   */
  public function getProgramsByTagId($id, $limit = 20, $offset = 0)
  {
    $qb = $this->createQueryBuilder('e');

    return $qb
      ->select('e')
      ->leftJoin('e.tags', 'f')
      ->where($qb->expr()->eq('e.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('f.id', ':id'))
      ->orderBy('e.uploaded_at', 'DESC')
      ->setParameter('id', $id)
      ->setFirstResult($offset)
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();
  }

  /**
   * @param array $program_ids
   *
   * @return mixed
   */
  public function getProgramDataByIds(Array $program_ids)
  {
    $qb = $this->createQueryBuilder('p');

    return $qb
      ->select(['p.id', 'p.name', 'p.uploaded_at', 'u.username'])
      ->innerJoin('p.user', 'u')
      ->where($qb->expr()->eq('p.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('p.private', $qb->expr()->literal(false)))
      ->andWhere('p.id IN (:program_ids)')
      ->setParameter('program_ids', $program_ids)
      ->distinct()
      ->getQuery()
      ->getResult();
  }

  /**
   * @param     $name
   * @param int $limit
   * @param int $offset
   *
   * @return mixed
   */
  public function getProgramsByExtensionName($name, $limit = 20, $offset = 0)
  {
    $qb = $this->createQueryBuilder('e');

    return $qb
      ->select('e')
      ->leftJoin('e.extensions', 'f')
      ->where($qb->expr()->eq('e.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('f.name', ':name'))
      ->orderBy('e.uploaded_at', 'DESC')
      ->setParameter('name', $name)
      ->setFirstResult($offset)
      ->setMaxResults($limit)
      ->getQuery()
      ->getResult();
  }

  /**
   * @param $query
   *
   * @return int
   */
  public function searchTagCount($query)
  {
    $query = str_replace("yahoo", "", $query);

    $qb = $this->createQueryBuilder('e');

    $result = $qb
      ->select('e')
      ->leftJoin('e.tags', 't')
      ->where($qb->expr()->eq('e.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('t.id', ':id'))
      ->andWhere($qb->expr()->eq('e.private', $qb->expr()->literal(false)))
      ->setParameter('id', $query)
      ->getQuery()
      ->getResult();

    return count($result);
  }

  /**
   * @param $query
   *
   * @return int
   */
  public function searchExtensionCount($query)
  {
    $qb = $this->createQueryBuilder('e');

    $result = $qb
      ->select('e')
      ->leftJoin('e.extensions', 't')
      ->where($qb->expr()->eq('e.visible', $qb->expr()->literal(true)))
      ->andWhere($qb->expr()->eq('t.name', ':name'))
      ->andWhere($qb->expr()->eq('e.private', $qb->expr()->literal(false)))
      ->setParameter('name', $query)
      ->getQuery()
      ->getResult();

    return count($result);
  }


  /**
   * @param        $id
   * @param string $flavor
   *
   * @return int
   */
  public function getRecommendedProgramsCount($id, $flavor = 'pocketcode')
  {
    $qb_tags = $this->createQueryBuilder('e');

    $result = $qb_tags
      ->select('t.id')
      ->leftJoin('e.tags', 't')
      ->where($qb_tags->expr()->eq('e.id', ':id'))
      ->setParameter('id', $id)
      ->getQuery()
      ->getResult();

    $qb_extensions = $this->createQueryBuilder('e');

    $result_2 = $qb_extensions
      ->select('x.id')
      ->leftJoin('e.extensions', 'x')
      ->where($qb_tags->expr()->eq('e.id', ':id'))
      ->setParameter('id', $id)
      ->getQuery()
      ->getResult();

    $tag_ids = array_map('current', $result);
    $extensions_id = array_map('current', $result_2);

    $dql = "SELECT COUNT(e.id) cnt, e.id
          FROM App\Entity\Program e
          LEFT JOIN e.tags t
          LEFT JOIN e.extensions x
          WHERE t.id IN (
            :tag_ids
          )
          OR x.id IN (
            :extension_ids
          )
          AND e.flavor = :flavor
          AND e.id != :id
          AND e.visible = true
          GROUP BY e.id
          ORDER BY cnt DESC
        ";

    $qb_program = $this->createQueryBuilder('e');
    $q2 = $qb_program->getEntityManager()->createQuery($dql);
    $q2->setParameters(['id' => $id, 'tag_ids' => $tag_ids, 'extension_ids' => $extensions_id, 'flavor' => $flavor]);

    return count($q2->getResult());

  }

  /**
   * @param        $id
   * @param string $flavor
   * @param        $limit
   * @param        $offset
   *
   * @return array
   */
  public function getRecommendedProgramsById($id, $flavor, $limit, $offset)
  {

    $qb_tags = $this->createQueryBuilder('e');

    $result = $qb_tags
      ->select('t.id')
      ->leftJoin('e.tags', 't')
      ->where($qb_tags->expr()->eq('e.id', ':id'))
      ->setParameter('id', $id)
      ->getQuery()
      ->getResult();

    $qb_extensions = $this->createQueryBuilder('e');

    $result_2 = $qb_extensions
      ->select('x.id')
      ->leftJoin('e.extensions', 'x')
      ->where($qb_tags->expr()->eq('e.id', ':id'))
      ->setParameter('id', $id)
      ->getQuery()
      ->getResult();

    $tag_ids = array_map('current', $result);
    $extensions_id = array_map('current', $result_2);

    $dql = "SELECT COUNT(e.id) cnt, e.id
          FROM App\Entity\Program e
          LEFT JOIN e.tags t
          LEFT JOIN e.extensions x
          WHERE (t.id IN (
            :tag_ids
          )
          OR x.id IN (
            :extension_ids
          ))
          AND e.flavor = :flavor
          AND e.id != :pid
          AND e.visible = TRUE 
          GROUP BY e.id
          ORDER BY cnt DESC
        ";

    $qb_program = $this->createQueryBuilder('e');
    $q2 = $qb_program->getEntityManager()->createQuery($dql);
    $q2->setParameters(['pid' => $id, 'tag_ids' => $tag_ids, 'extension_ids' => $extensions_id, 'flavor' => $flavor]);

    $q2->setFirstResult($offset);
    $q2->setMaxResults($limit);
    $id_list = array_map(function ($value) {
      return $value['id'];
    }, $q2->getResult());

    $programs = [];
    foreach ($id_list as $id)
    {
      array_push($programs, $this->find($id));
    }

    return $programs;

  }
}
