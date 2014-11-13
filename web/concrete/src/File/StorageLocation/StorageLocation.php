<?php
namespace Concrete\Core\File\StorageLocation;
use Concrete\Core\File\StorageLocation\Configuration\Configuration;
use Concrete\Core\File\StorageLocation\Configuration\ConfigurationInterface;
use Database;
use Core;
/**
 * @Entity
 * @Table(name="FileStorageLocations")
 */
class StorageLocation
{

    /**
     * @Column(type="text")
     */
    protected $fslName;

    /**
     * @Column(type="object")
     */
    protected $fslConfiguration;

    /**
     * @Id @Column(type="integer")
     * @GeneratedValue
     */
    protected $fslID;

    /**
     * @Column(type="boolean")
     */
    protected $fslIsDefault = false;

    public function getID()
    {
        return $this->fslID;
    }

    public function getName()
    {
        return $this->fslName;
    }

    public function setName($fslName)
    {
        $this->fslName = $fslName;
    }

    public function setIsDefault($fslIsDefault)
    {
        $this->fslIsDefault = $fslIsDefault;
    }

    /**
     * @return \Concrete\Core\File\StorageLocation\Configuration\ConfigurationInterface
     */
    public function getConfigurationObject()
    {
        return $this->fslConfiguration;
    }

    public function isDefault()
    {
        return $this->fslIsDefault;
    }

    public function setConfigurationObject($configuration)
    {
        $this->fslConfiguration = $configuration;
    }

    public function getTypeObject()
    {
        $configuration = $this->getConfigurationObject();
        $type = $configuration->getTypeObject();
        return $type;
    }

    public static function add(ConfigurationInterface $configuration, $fslName, $fslIsDefault = false)
    {
        $default = self::getDefault();

        $db = Database::get();
        $em = $db->getEntityManager();
        $o = new static();
        $o->fslName = $fslName;
        $o->fslIsDefault = $fslIsDefault;
        $o->fslConfiguration = $configuration;
        $em->persist($o);

        if ($fslIsDefault && is_object($default)) {
            $default->fslIsDefault = false;
            $em->persist($default);
        }

        $em->flush();

        return $o;
    }

    public static function getByID($id)
    {
        $db = Database::get();
        $em = $db->getEntityManager();
        $r = $em->find('\Concrete\Core\File\StorageLocation\StorageLocation', intval($id));
        return $r;
    }

    public static function getList()
    {
        $db = Database::get();
        $em = $db->getEntityManager();
        return $em->getRepository('\Concrete\Core\File\StorageLocation\StorageLocation')->findBy(
            array(), array('fslID' => 'asc')
        );
    }

    /**
     * @return StorageLocation
     */
    public static function getDefault()
    {
        $db = Database::get();
        $em = $db->getEntityManager();
        $location = $em->getRepository('\Concrete\Core\File\StorageLocation\StorageLocation')->findOneBy(
            array('fslIsDefault' => true
            ));
        return $location;
    }

    /**
     * Returns the proper file system object for the current storage location, by mapping
     * it through Flysystem
     * @return \Concrete\Flysystem\Filesystem
     */
    public function getFileSystemObject()
    {
        $adapter = $this->fslConfiguration->getAdapter();
        $filesystem = new \Concrete\Flysystem\Filesystem($adapter);
        return $filesystem;
    }

    public function delete()
    {
        $default = self::getDefault();
        $db = Database::get();

        $fIDs = $db->GetCol('select fID from Files where fslID = ?', array($this->getID()));
        foreach($fIDs as $fID) {
            $file = \File::getByID($fID);
            if (is_object($file)) {
                $file->setFileStorageLocation($default);
            }
        }

        $em = $db->getEntityManager();
        $em->remove($this);
        $em->flush();
    }

    public function save()
    {
        $default = self::getDefault();

        $db = Database::get();
        $em = $db->getEntityManager();
        $em->persist($this);

        if ($this->isDefault() && is_object($default) && $default->getID() != $this->getID()) {
            $default->setIsDefault(false);
            $em->persist($default);
        }

        $em->flush();
    }

}
