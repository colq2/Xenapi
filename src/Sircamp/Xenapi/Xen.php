<?php namespace Sircamp\Xenapi;

use Respect\Validation\Validator;
use Sircamp\Xenapi\Connection\XenConnection;
use Sircamp\Xenapi\Connection\XenResponse;
use Sircamp\Xenapi\Element\XenHost;
use Sircamp\Xenapi\Element\XenNetwork;
use Sircamp\Xenapi\Element\XenPhysicalInterface;
use Sircamp\Xenapi\Element\XenPool;
use Sircamp\Xenapi\Element\XenStorageRepository;
use Sircamp\Xenapi\Element\XenVirtualBlockDevice;
use Sircamp\Xenapi\Element\XenVirtualDiskImage;
use Sircamp\Xenapi\Element\XenVirtualInterface;
use Sircamp\Xenapi\Element\XenVirtualLAN;
use Sircamp\Xenapi\Element\XenVirtualMachine;
use Sircamp\Xenapi\Exception\XenException;

class Xen
{

	private $xenConnection = null;

	/*
	 * Implicit methods
	 *
	 * a constructor (usually called "create"); --> is implemented in the class
	 * a destructor (usually called "destroy"); --> is implemented in the class
	 * "get_record"; --> is implemented in the class
	 *
	 * "get_by_name_label";     --> is called getXXByNameLabel()
	 * "get_by_uuid"            --> is called getXXByUUid()
	 * "get_all".               --> is called getAllXX()
	*/


	/**
	 * Xen constructor.
	 *
	 * @param $url
	 * @param $user
	 * @param $password
	 */
	public function __construct(string $url, string $user, string $password)
	{
		if (!Validator::url()->validate($url))
		{
			throw new \InvalidArgumentException("'url' value mast be an ipv4 address", 1);
		}
		if (!Validator::stringType()->validate($user))
		{
			throw new \InvalidArgumentException("'user' value mast be an non empty string", 1);
		}

		if (!Validator::stringType()->validate($password))
		{
			throw new \InvalidArgumentException("'password' value mast be an non empty string", 1);
		}

		$this->xenConnection = new XenConnection();
		try
		{
			$this->xenConnection->_setServer($url, $user, $password);
		}
		catch (\Exception $e)
		{
			die($e->getMessage());
		}
	}

	/**
	 * This function delegates function calls like $xen->VM__get_all();
	 * to the _call function of thr XenConnection object and returns the XenResponse
	 *
	 *
	 * @param string $name
	 * @param array  $args
	 *
	 * @return XenResponse
	 */
	public function __call(string $name, array $args = array()): XenResponse
	{
		return $this->getXenConnection()->__call($name, $args);
	}

	/**
	 * This returns the XenConnection object
	 *
	 * @return null|XenConnection
	 */
	public function getXenConnection()
	{
		return $this->xenConnection;
	}


	public function enableDebug(string $filename)
	{
		XenConnection::$debug      = true;
		XenConnection::$debug_file = $filename;
	}

	public function disableDebug()
	{
		XenConnection::$debug = false;
	}
	//VirtualMachine
//	/**
//	 * NOT RECOMMENDED and not implemented use $vm->clone or $vm->copy
//	 */
//	public function createVirtualMachine()
//	{
//
//	}

	/**
	 * Return a list of all the VMs known to the system
	 *
	 * @return array
	 */
	public function getAllVirtualMachines(): array
	{
		$refIDs = $this->xenConnection->call('VM.get_all');
		$vms    = array();
		foreach ($refIDs as $refID)
		{
			$vms[] = new XenVirtualMachine($this->xenConnection, $refID);
		}

		return $vms;
	}

	/**
	 * Return a array VMs and VM records for all VMs known to the system.
	 *
	 * @return array With the Form: [0 => ['vm' => vm_object, 'record'=> record_array]]
	 */
	public function getAllVirtualMachineRecords(): array
	{
		$map   = $this->xenConnection->call('VM.get_all_records');
		$vmMap = array();

		foreach ($map as $refID => $record)
		{
			$vm      = new XenVirtualMachine($this->xenConnection, $refID);
			$vmMap[] = ['vm' => $vm, 'record' => $record];
		}

		return $vmMap;
	}


	/**
	 * Get all the VM instances with the given label.
	 *
	 * @param String $name Label of the object to return
	 *
	 * @return array
	 */
	public function getVirtualMachineByNameLabel(String $name): array
	{
		$refArray = $this->xenConnection->call('VM.get_by_name_label', [$name]);
		$vmArray  = array();

		foreach ($refArray as $refID)
		{
			$vmArray[] = new XenVirtualMachine($this->xenConnection, $refID);
		}

		return $vmArray;
	}

	/**
	 * Get a the VM instance with the specified UUID.
	 *
	 * @param String $uuid UUID of the object to return
	 *
	 * @return XenVirtualMachine
	 */
	public function getVirtualMachineByUUID(String $uuid): XenVirtualMachine
	{
		$refID = $this->xenConnection->call('VM.get_by_uuid', [$uuid]);

		return new XenVirtualMachine($this->xenConnection, $refID);
	}

	/**
	 * Import an XVA from a URI
	 *
	 * @param string               $url          The URL of the XVA file
	 * @param XenStorageRepository $sr           The destination SR for the disks
	 * @param bool                 $full_restore Perform a full restore
	 * @param bool                 $force        Force the import
	 *
	 * @return array
	 */
	public function importVirtualMachines(string $url, XenStorageRepository $sr, bool $full_restore = false, bool $force = false): array
	{
		$refIDs  = $this->xenConnection->call('VM.import', [$url, $sr->getRefID(), $full_restore, $force]);
		$vmArray = array();

		foreach ($refIDs as $refID)
		{
			$vmArray[] = new XenVirtualMachine($this->xenConnection, $refID);
		}

		return $vmArray;
	}

	/**
	 * Import using a conversion service.
	 *
	 * @param string               $type          Type of the conversion
	 * @param string               $username      Admin username on the hst
	 * @param string               $password      Password on the host
	 * @param XenStorageRepository $sr            The destination SR
	 * @param array                $remote_config Remote configuration options
	 */
	public function importConvertVirtualMachines(string $type, string $username, string $password, XenStorageRepository $sr, array $remote_config = array())
	{
		$this->xenConnection->call('VM.import_convert', [$type, $username, $password, $sr->getRefID(), $remote_config]);
	}

	//Host

	/**
	 * Return a list of all the hosts known to the system.
	 *
	 * @return array
	 */
	public function getAllHosts(): array
	{
		$refIDs = $this->xenConnection->call('host.get_all');
		$vms    = array();
		foreach ($refIDs as $refID)
		{
			$vms[] = new XenHost($this->xenConnection, $refID);
		}

		return $vms;
	}

	/**
	 * Return a with Hosts and Hosts records for all VMs known to the system.
	 *
	 * @return array With the Form: [0 => ['host' => host_object, 'record'=> record_array]]
	 */
	public function getAllHostRecords()
	{
		$map       = $this->xenConnection->call('host.get_all_records');
		$hostArray = array();

		foreach ($map as $refID => $record)
		{
			$host        = new XenHost($this->xenConnection, $refID);
			$hostArray[] = ['host' => $host, 'record' => $record];
		}

		return $hostArray;
	}

	/**
	 * Get all the host instances with the given label.
	 *
	 * @param String $name label of object to return
	 *
	 * @return array
	 */
	public function getHostByNameLabel(String $name): array
	{
		$refArray  = $this->xenConnection->call('host.get_by_name_label', [$name]);
		$hostArray = array();

		foreach ($refArray as $refID)
		{
			$hostArray[] = new XenVirtualMachine($this->xenConnection, $refID);
		}

		return $hostArray;
	}

	/**
	 * Get the host instance with the specified UUID.
	 *
	 * @param String $uuid UUID of object to return
	 *
	 * @return XenHost
	 */
	public function getHostByUUID(String $uuid): XenHost
	{
		$xenResponse = $this->xenConnection->call('host.get_by_uuid', [$uuid]);
		$refID       = $xenResponse;

		return new XenHost($this->xenConnection, $refID);
	}

	//TODO: methods like local_management_reconfigure, management_disable, management_reconfigure

	/**
	 * Shuts the agent down after a 10 second pause. WARNING: this is a dangerous operation.
	 * Any operations in progress will be aborted, and unrecoverable data loss may occur.
	 * The caller is responsible for ensuring that there are no operations in progress when this method is called.
	 */
	public function shutdownAgent()
	{
		$this->xenConnection->call('host.shutdown_agent');

	}

	//Network

	/**
	 * Create a new network instance, and return its handle.
	 * The args are: name_label, name_description, MTU, other_config*, bridge, managed, tags (* = non-optional).
	 *
	 * @param string $name_label
	 * @param string $name_description
	 * @param int    $MTU
	 * @param array  $other_config
	 * @param string $bridge
	 * @param bool   $managed
	 * @param array  $tags
	 *
	 * @return XenNetwork
	 */
	public function createNetwork(string $name_label, string $name_description = "", int $MTU = 1500, array $other_config = array(), string $bridge = '', bool $managed = true, array $tags = array())
	{

		//Generate record
		if (!array_key_exists('automatic', $other_config))
		{
			$other_config['automatic'] = 'false';
		}

		$record = compact('name_label', 'name_description', 'MTU', 'other_config', 'bridge', 'managed', 'tags');

		$xenResponse = $this->xenConnection->call('network.create', [$record]);
		$refID       = $xenResponse;

		return new XenNetwork($this->xenConnection, $refID);
	}

	/**
	 * Return a array of all the networks known to the system.
	 *
	 * @return array
	 */
	public function getAllNetworks(): array
	{
		$refIDs = $this->xenConnection->call('network.get_all');
		$vms    = array();
		foreach ($refIDs as $refID)
		{
			$vms[] = new XenNetwork($this->xenConnection, $refID);
		}

		return $vms;
	}

	/**
	 * Return an array with networks and network records for all networks known to the system.
	 *
	 * @return array With the Form: [0 => ['network' => network_object, 'record'=> record_array]]
	 */
	public function getAllNetworkRecords(): array
	{
		$map          = $this->xenConnection->call('network.get_all_records');
		$networkArray = array();

		foreach ($map as $refID => $record)
		{
			$network        = new XenNetwork($this->xenConnection, $refID);
			$networkArray[] = ['network' => $network, 'record' => $record];
		}

		return $networkArray;
	}

	/**
	 * Get all the network instances with the given label.
	 *
	 * @param String $name
	 *
	 * @return array
	 */
	public function getNetworkByNameLabel(String $name): array
	{
		$refArray     = $this->xenConnection->call('network.get_by_name_label', [$name]);
		$networkArray = array();

		foreach ($refArray as $refID)
		{
			$networkArray[] = new XenNetwork($this->xenConnection, $refID);
		}

		return $networkArray;
	}

	/**
	 * Get the network instance with the specified UUID.
	 *
	 * @param String $uuid
	 *
	 * @return XenNetwork
	 */
	public function getNetworkByUUID(String $uuid): XenNetwork
	{
		$xenResponse = $this->xenConnection->call('network.get_by_uuid', [$uuid]);
		$refID       = $xenResponse;

		return new XenNetwork($this->xenConnection, $refID);
	}

	//VirtualLAN

	/**
	 * Create a VLAN mux/demuxer
	 *
	 *
	 * @param XenPhysicalInterface $xenPIF     PIF which receives the tagged traffic
	 * @param int                  $tag        VLAN tag to use
	 * @param XenNetwork           $xenNetwork Network to receive the untagged traffic
	 *
	 * @return XenVirtualLAN
	 */
	public function createVirtualLAN(XenPhysicalInterface $xenPIF, int $tag, XenNetwork $xenNetwork): XenVirtualLAN
	{
		$refID = $this->xenConnection->call('VLAN.create', [$xenPIF->getRefID(), $tag, $xenNetwork->getRefID()]);

		return new XenVirtualLAN($this->xenConnection, $refID);
	}

	/**
	 * Return a list of all the VLANs known to the system.
	 *
	 * @return array
	 */
	public function getAllVirtualLANs(): array
	{
		$refIDs = $this->xenConnection->call('VLAN.get_all');
		$vms    = array();
		foreach ($refIDs as $refID)
		{
			$vms[] = new XenVirtualLAN($this->xenConnection, $refID);
		}

		return $vms;
	}

	/**
	 * Return a array with VLANs and VLAN records for all VLANs known to the system.
	 *
	 * @return array With the Form: [0 => ['network' => network_object, 'record'=> record_array]]
	 */
	public function getAllVirtualLANRecords(): array
	{
		$map       = $this->xenConnection->call('VLAN.get_all_records');
		$vlanArray = array();

		foreach ($map as $refID => $record)
		{
			$vlan        = new XenVirtualLAN($this->xenConnection, $refID);
			$vlanArray[] = ['vlan' => $vlan, 'record' => $record];
		}

		return $vlanArray;
	}

	/**
	 * Get a reference to the VLAN instance with the specified UUID.
	 *
	 * @param String $uuid
	 *
	 * @return XenVirtualLAN
	 */
	public function getVirtualLANByUUID(String $uuid): XenVirtualLAN
	{
		$xenResponse = $this->xenConnection->call('VLAN.get_by_uuid', [$uuid]);
		$refID       = $xenResponse;

		return new XenVirtualLAN($this->xenConnection, $refID);
	}


	//Storage Repositories

	public function createStorageRepository()
	{
		//TODO: implement
		throw new XenException(['Not implemented yet :('], 0);
	}

	/**
	 * Return a list of all the SRs known to the system.
	 *
	 * @return array All SRs
	 */
	public function getAllStorageRepositories(): array
	{
		$refIDs = $this->xenConnection->call('SR.get_all');
		$vms    = array();
		foreach ($refIDs as $refID)
		{
			$vms[] = new XenStorageRepository($this->xenConnection, $refID);
		}

		return $vms;
	}

	/**
	 * Return an array with StorageRepositories and Storage Repositories records for all VMs known to the system.
	 *
	 * @return array With the Form: [0 => ['sr' => sr_object, 'record'=> record_array]]
	 */
	public function getAllStorageRepositoryRecords(): array
	{
		$map     = $this->xenConnection->call('SR.get_all_records');
		$srArray = array();

		foreach ($map as $refID => $record)
		{
			$sr        = new XenNetwork($this->xenConnection, $refID);
			$srArray[] = ['storageRepository' => $sr, 'record' => $record];
		}

		return $srArray;
	}

	/**
	 * Get all the SR instances with the given label.
	 *
	 * @param string $name label of object to return
	 *
	 * @return array with all SR matching the name label
	 */
	public function getStorageRepositoryByNameLabel(string $name): array
	{
		$refArray     = $this->xenConnection->call('SR.get_by_name_label', [$name]);
		$networkArray = array();

		foreach ($refArray as $refID)
		{
			$networkArray[] = new XenStorageRepository($this->xenConnection, $refID);
		}

		return $networkArray;
	}

	/**
	 * Get the SR instance with the specified UUID.
	 *
	 * @param String $uuid
	 *
	 * @return XenStorageRepository
	 */
	public function getStorageRepositoryByUUID(String $uuid): XenStorageRepository
	{
		$xenResponse = $this->xenConnection->call('SR.get_by_uuid', [$uuid]);
		$refID       = $xenResponse;

		return new XenStorageRepository($this->xenConnection, $refID);
	}

	/**
	 * Return a set of all the SR types supported by the system
	 *
	 * @return array
	 */
	public function getSupportedStorageRepositoriesTypes(): array
	{
		return $this->xenConnection->call('SR.get_supported_types');
	}

	/**
	 * Introduce a new Storage Repository into the managed system
	 *
	 * @param array  $sm_config Storage backend specific configuration options
	 * @param string $name_label
	 * @param string $name_description
	 * @param string $type
	 * @param string $content_type
	 * @param bool   $shared
	 *
	 * @return XenStorageRepository
	 * @internal param string $uuid
	 */
	public function introduceStorageRepository(array $sm_config, string $name_label, string $name_description = '', string $type = 'nfs', string $content_type = '', bool $shared = true): XenStorageRepository
	{
		$xenResponse = $this->xenConnection->call('SR.introduce', ['', $name_label, $name_description, $type, $content_type, $shared, $sm_config]);
		$refID       = $xenResponse;

		return new XenStorageRepository($this->xenConnection, $refID);
	}

	public function probeStorageRepository()
	{
		//TODO: implement
		throw new XenException(['Not implemented yet :('], 0);
	}

	//Virtual Disk Image

	public function createVirtualDiskImage()
	{
		//TODO: implement
		throw new XenException(['Not implemented yet :('], 0);
	}

	public function dbIntroduceVirtualDiskImage()
	{
		//TODO: implement
		throw new XenException(['Not implemented yet :('], 0);
	}

	/**
	 * Return a list of all the VDIs known to the system.
	 *
	 * @return array
	 */
	public function getAllVirtualDiskImages(): array
	{
		$refIDs = $this->xenConnection->call('VDI.get_all');
		$vdis   = array();
		foreach ($refIDs as $refID)
		{
			$vdis[] = new XenVirtualDiskImage($this->xenConnection, $refID);
		}

		return $vdis;
	}

	/**
	 * Return a array with VDIs and VDI records for all VDIs known to the system.
	 *
	 * @return array With the Form: [0 => ['vdi' => vdi_object, 'record'=> record_array]]
	 */
	public function getAllVirtualDiskImageRecords(): array
	{
		$map      = $this->xenConnection->call('VDI.get_all_records');
		$vdiArray = array();

		foreach ($map as $refID => $record)
		{
			$vlan       = new XenVirtualDiskImage($this->xenConnection, $refID);
			$vdiArray[] = ['vdi' => $vlan, 'record' => $record];
		}

		return $vdiArray;
	}

	/**
	 * Get all the SR instances with the given label.
	 *
	 * @param string $name label of object to return
	 *
	 * @return array with all SR matching the name label
	 */
	public function getVirtualDiskImagesByNameLabel(string $name): array
	{
		$refArray = $this->xenConnection->call('VDI.get_by_name_label', [$name]);
		$vdiArray = array();

		foreach ($refArray as $refID)
		{
			$vdiArray[] = new XenVirtualDiskImage($this->xenConnection, $refID);
		}

		return $vdiArray;
	}

	/**
	 * Get a reference to the VDI instance with the specified UUID.
	 *
	 * @param String $uuid
	 *
	 * @return XenVirtualDiskImage
	 */
	public function getVirtualDiskImageByUUID(String $uuid): XenVirtualDiskImage
	{
		$xenResponse = $this->xenConnection->call('VDI.get_by_uuid', [$uuid]);
		$refID       = $xenResponse;

		return new XenVirtualDiskImage($this->xenConnection, $refID);
	}

	public function introduceVirtualDiskImage()
	{
		//TODO: implement
		throw new XenException(['Not implemented yet :('], 0);
	}


	//Virtual Block Device

	/**
	 * @param XenVirtualMachine   $vm
	 * @param XenVirtualDiskImage $vdi
	 * @param string              $user_device Position of the disk
	 * @param bool                $bootable
	 * @param string              $mode
	 * @param string              $type
	 * @param bool                $unpluggable
	 * @param bool                $empty
	 * @param array               $other_config
	 * @param string              $qos_algorithm_type
	 * @param array               $qos_algorithm_params
	 *
	 * @return XenVirtualBlockDevice
	 */
	public function createVirtualBlockDevice(XenVirtualMachine $vm, XenVirtualDiskImage $vdi, string $user_device = '0', bool $bootable = true, string $mode = 'RW', string $type = 'Disk', bool $unpluggable = false, bool $empty = false, array $other_config = array(), string $qos_algorithm_type = '', array $qos_algorithm_params = array())
	{
		//create record
		$other_config['generated_with'] = "XenAPI";
		if (empty($qos_algorithm_params))
		{
			$qos_algorithm_params['generated_with'] = 'api';
		}

		$record = [
			'VM'                   => $vm->getRefID(),
			'VDI'                  => $vdi->getRefID(),
			'userdevice'           => $user_device,
			'bootable'             => $bootable,
			'mode'                 => $mode,
			'type'                 => $type,
			'unpluggable'          => $unpluggable,
			'empty'                => $empty,
			'other_config'         => $other_config,
			'qos_algorithm_type'   => $qos_algorithm_type,
			'qos_algorithm_params' => $qos_algorithm_params,
		];

		$xenResponse = $this->xenConnection->call('VBD.create', [$record]);
		$refID       = $xenResponse;
		$vbd         = new XenVirtualBlockDevice($this->xenConnection, $refID);

		return $vbd;
	}

	/**
	 * Return a list of all the VBDs known to the system.
	 *
	 * @return array
	 */
	public function getAllVirtualBlockDevices(): array
	{
		$refIDs = $this->xenConnection->call('VBD.get_all');
		$vbds   = array();
		foreach ($refIDs as $refID)
		{
			$vbds[] = new XenVirtualBlockDevice($this->xenConnection, $refID);
		}

		return $vbds;
	}

	/**
	 * Return a array with VBDs and VBD records for all VBDs known to the system.
	 *
	 * @return array With the Form: [0 => ['vbd' => vbd_object, 'record'=> record_array]]
	 */
	public function getAllVirtualBlockDeviceRecords(): array
	{
		$map      = $this->xenConnection->call('VBD.get_all_records');
		$vbdArray = array();

		foreach ($map as $refID => $record)
		{
			$vbd        = new XenVirtualBlockDevice($this->xenConnection, $refID);
			$vbdArray[] = ['vbd' => $vbd, 'record' => $record];
		}

		return $vbdArray;
	}

	/**
	 * Get a reference to the VBD instance with the specified UUID.
	 *
	 * @param String $uuid
	 *
	 * @return XenVirtualBlockDevice
	 */
	public function getVirtualBlockDeviceByUUID(String $uuid): XenVirtualBlockDevice
	{
		$xenResponse = $this->xenConnection->call('VBD.get_by_uuid', [$uuid]);
		$refID       = $xenResponse;

		return new XenVirtualBlockDevice($this->xenConnection, $refID);
	}



	//Virtual Interface

	/**
	 * Create a new VIF instance, and return its handle.
	 * You have to provide at least a XenNetwork and a XenVirtualMachine
	 *
	 * The other args a optional
	 *
	 * @param string            $device
	 * @param XenNetwork        $network
	 * @param XenVirtualMachine $vm
	 * @param string            $MAC
	 * @param int               $MTU
	 * @param array             $other_config
	 * @param string            $qos_algorithm_type
	 * @param array             $qos_algorithm_params
	 * @param string            $locking_mode
	 * @param array             $ipv4_allowed
	 * @param array             $ipv6_allowed
	 *
	 * @return XenVirtualInterface
	 */
	public function createVirtualInterface(XenNetwork $network, XenVirtualMachine $vm, string $device = '0', string $MAC = "", int $MTU = 1500, array $other_config = array(), string $qos_algorithm_type = '', array $qos_algorithm_params = array(), string $locking_mode = "network_default", array $ipv4_allowed = array(), array $ipv6_allowed = array()): XenVirtualInterface
	{
		//Create record
		$other_config['generated_with'] = 'api';

		if (empty($qos_algorithm_params))
		{
			$qos_algorithm_params['generated_with'] = 'api';
		}

		$record = [
			'device'               => $device,
			'network'              => $network->getRefID(),
			'VM'                   => $vm->getRefID(),
			'MAC'                  => $MAC,
			'MTU'                  => $MTU,
			'other_config'         => $other_config,
			'qos_algorithm_type'   => $qos_algorithm_type,
			'qos_algorithm_params' => $qos_algorithm_params,
			'locking_mode'         => $locking_mode
		];


		$xenResponse = $this->xenConnection->call('VIF.create', [$record]);
		$refID       = $xenResponse;
		$vif         = new XenVirtualInterface($this->xenConnection, $refID);

		//add ips
		$vif->setIPv4Allowed($ipv4_allowed);
		$vif->setIPv6Allowed($ipv6_allowed);

		return $vif;
	}

	/**
	 * Return a list of all the VIFs known to the system.
	 *
	 * @return array
	 */
	public function getAllVirtualInterfaces(): array
	{
		$refIDs = $this->xenConnection->call('VIF.get_all');
		$vdis   = array();
		foreach ($refIDs as $refID)
		{
			$vdis[] = new XenVirtualInterface($this->xenConnection, $refID);
		}

		return $vdis;
	}

	/**
	 * Return a array with VIFs and VIF records for all VIFs known to the system.
	 *
	 * @return array With the Form: [0 => ['vif' => vif_object, 'record'=> record_array]]
	 */
	public function getAllVirtualInterfaceRecords(): array
	{
		$map      = $this->xenConnection->call('VIF.get_all_records');
		$vifArray = array();

		foreach ($map as $refID => $record)
		{
			$vif        = new XenVirtualInterface($this->xenConnection, $refID);
			$vifArray[] = ['vif' => $vif, 'record' => $record];
		}

		return $vifArray;
	}

	/**
	 * Get a reference to the VIF instance with the specified UUID.
	 *
	 * @param String $uuid
	 *
	 * @return XenVirtualInterface
	 */
	public function getVirtualInterfaceByUUID(String $uuid): XenVirtualInterface
	{
		$xenResponse = $this->xenConnection->call('VIF.get_by_uuid', [$uuid]);
		$refID       = $xenResponse;

		return new XenVirtualInterface($this->xenConnection, $refID);
	}

	//Physical Interfaces

	/**
	 * Return a list of all the PIFs known to the system.
	 *
	 * @return array
	 */
	public function getAllPhysicalInterfaces(): array
	{
		$refIDs = $this->xenConnection->call('PIF.get_all');
		$pifs   = array();
		foreach ($refIDs as $refID)
		{
			$pifs[] = new XenPhysicalInterface($this->xenConnection, $refID);
		}

		return $pifs;
	}

	/**
	 * Return a array with PIFs and PIF records for all PIFs known to the system.
	 *
	 * @return array With the Form: [0 => ['pif' => vdi_object, 'record'=> record_array]]
	 */
	public function getAllPhysicalInterfaceRecords(): array
	{
		$map      = $this->xenConnection->call('PIF.get_all_records');
		$pifArray = array();

		foreach ($map as $refID => $record)
		{
			$pif        = new XenPhysicalInterface($this->xenConnection, $refID);
			$pifArray[] = ['pif' => $pif, 'record' => $record];
		}

		return $pifArray;
	}

	/**
	 * Get a reference to the PIF instance with the specified UUID.
	 *
	 * @param String $uuid
	 *
	 * @return XenPhysicalInterface
	 */
	public function getPhysicalInterfaceByUUID(String $uuid): XenPhysicalInterface
	{
		$xenResponse = $this->xenConnection->call('PIF.get_by_uuid', [$uuid]);
		$refID       = $xenResponse;

		return new XenPhysicalInterface($this->xenConnection, $refID);
	}

	/**
	 * Create a PIF object matching a particular network interface
	 *
	 * @param XenHost $host
	 * @param string  $MAC
	 * @param string  $device
	 * @param bool    $managed
	 *
	 * @return XenPhysicalInterface
	 */
	public function introducePhysicalInterface(XenHost $host, string $MAC = '', string $device = '0', bool $managed = true): XenPhysicalInterface
	{
		$refID = $this->xenConnection->call('PIF.introduce', [$host->getRefID(), $MAC, $device, $managed]);

		return new XenPhysicalInterface($this->xenConnection, $refID);
	}

	/**
	 * Scan for physical interfaces on a host and create PIF objects to represent them
	 *
	 * @param XenHost $host
	 */
	public function scanPhysicalInterface(XenHost $host)
	{
		$this->xenConnection->call('PIF.scan', [$host->getRefID()]);
	}


	//XenPool

	/**
	 * Install an SSL certificate pool-wide.
	 *
	 * @param string $name A name to give the certificate
	 * @param string $cert The certificate
	 */
	public function certificateInstall(string $name, string $cert)
	{
		$this->xenConnection->call('pool.certificate_install', [$name, $cert]);
	}

	/**
	 * List all installed SSL certificates.
	 *
	 * @return array
	 */
	public function certificateList(): array
	{
		return $this->xenConnection->call('pool.certificate_list');
	}

	/**
	 * Sync SSL certificates from master to slaves
	 */
	public function certificateSync()
	{
		$this->xenConnection->call('pool.certificate_sync');
	}

	/**
	 * Remove an SSL certificate.
	 *
	 * @param string $name The certificate name
	 */
	public function certificateUninstall(string $name)
	{
		$this->xenConnection->call('pool.certificate_uninstall', [$name]);
	}

	/**
	 * Create PIFs, mapping a network to the same physical interface/VLAN on each host.
	 * This call is deprecated: use Pool.create_VLAN_from_PIF instead.
	 *
	 * @param string     $device  Physical interface on which to create the VLAN
	 * @param XenNetwork $network Network to which this interface should be connected
	 * @param int        $tag     VLAN tag for the new interface
	 *
	 * @return array
	 */
	public function createVLAN(string $device, XenNetwork $network, int $tag): array
	{
		$refIDs   = $this->xenConnection->call('pool.create_VLAN', [$device, $network->getRefID(), $tag]);
		$pifArray = array();

		foreach ($refIDs as $refID)
		{
			$pifArray[] = new XenPhysicalInterface($this->xenConnection, $refID);
		}

		return $pifArray;
	}

	/**
	 * Create a pool-wide VLAN by taking the PIF.
	 *
	 * @param XenPhysicalInterface $pif     Physical interface an any particular host that identifies the PIF on which to create the (pool-wide) VLAND interface
	 * @param XenNetwork           $network Network to which this interface should be connected
	 * @param int                  $tag     VLAN tag for the new interface
	 *
	 * @return array
	 */
	public function createVLANFromPhysicalInterface(XenPhysicalInterface $pif, XenNetwork $network, int $tag)
	{
		$refIDs   = $this->xenConnection->call('pool.create_VLAN_from_PIF', [$pif->getRefID(), $network->getRefID(), $tag]);
		$pifArray = array();

		foreach ($refIDs as $refID)
		{
			$pifArray[] = new XenPhysicalInterface($this->xenConnection, $refID);
		}

		return $pifArray;
	}

	/**
	 * Install an SSL certificate revocation list, pool-wide.
	 *
	 * @param string $name A name to give the CRL
	 * @param string $cert The CRL
	 */
	public function crlInstall(string $name, string $cert)
	{
		$this->xenConnection->call('pool.crl_install', [$name, $cert]);
	}

	/**
	 * List all installed SSL certificate revocation lists.
	 *
	 * @return mixed
	 */
	public function crlList()
	{
		return $this->xenConnection->call('pool.crl_list');
	}

	/**
	 * Remove an SSL certificate revocation list.
	 *
	 * @param string $name The CRL name
	 */
	public function crlUninstall(string $name)
	{
		$this->xenConnection->call('pool.crl_uninstall', [$name]);
	}

	/**
	 * Permanently deconfigures workload balancing monitoring on this pool
	 */
	public function deconfigureWLB()
	{
		$this->xenConnection->call('pool.deconfigure_wlb');
	}

	/**
	 * Perform an orderly handover of the role of master to the referenced host.
	 *
	 * @param XenHost $host The host who should become the new master
	 */
	public function designateNewMaster(XenHost $host)
	{
		$this->xenConnection->call('pool.designate_new_master', [$host->getRefID()]);
	}

	/**
	 * Turn off High Availability mode
	 */
	public function disableHA()
	{
		$this->xenConnection->call('pool.disable_ha');
	}

	/**
	 * Disable the redo log if in use, unless HA is enabled.
	 */
	public function disableRedoLog()
	{
		$this->xenConnection->call('pool.disable_redo_log');
	}

	/**
	 * Instruct a pool master to eject a host from the pool
	 *
	 * @param XenHost $host The host to eject
	 */
	public function eject(XenHost $host)
	{
		$this->xenConnection->call('pool.eject', [$host->getRefID()]);
	}

	/**
	 * Instruct a slave already in a pool that the master has changed
	 *
	 * @param string $master The hostname of the master
	 */
	public function emergencyResetMaster(string $master)
	{
		$this->xenConnection->call('pool.emergency_reset_master', [$master]);
	}

	/**
	 * Instruct host that's currently a slave to transition to being master
	 */
	public function emergencyTransitionToMaster()
	{
		$this->xenConnection->call('pool.emergency_transition_to_master');
	}

	/**
	 * Turn on High Availability mode
	 *
	 * @param array $heartbeat_srs Set of SRs to use for storage heartbeating
	 * @param array $config        Detailed HA configuration to apply
	 */
	public function enableHA(array $heartbeat_srs, array $config)
	{
		$refIDs = array();
		foreach ($heartbeat_srs as $sr)
		{
			$refIDs[] = $sr->getRefID();
		}

		$this->xenConnection->call('pool.enable_ha', [$refIDs, $config]);
	}

	/**
	 * Enable the redo log on the given SR and start using it, unless HA is enabled.
	 *
	 * @param XenStorageRepository $sr SR to hold the redo log.
	 */
	public function enableRedoLog(XenStorageRepository $sr)
	{
		$this->xenConnection->call('pool.enable_redo_log', [$sr->getRefID()]);
	}

	/**
	 * Return a list of all the pools known to the system.
	 *
	 * @return array
	 */
	public function getAllPools(): array
	{
		$refIDs = $this->xenConnection->call('pool.get_all');
		$pools  = array();
		foreach ($refIDs as $refID)
		{
			$pools[] = new XenPool($this->xenConnection, $refID);
		}

		return $pools;
	}

	/**
	 * Return a array with pools and pool records for all pools known to the system.
	 *
	 * @return array With the Form: [0 => ['pool' => vdi_object, 'record'=> record_array]]
	 */
	public function getAllPoolRecords(): array
	{
		$map       = $this->xenConnection->call('pool.get_all_records');
		$poolArray = array();

		foreach ($map as $refID => $record)
		{
			$pool        = new XenPool($this->xenConnection, $refID);
			$poolArray[] = ['pool' => $pool, 'record' => $record];
		}

		return $poolArray;
	}

	/**
	 * Get a reference to the pool instance with the specified UUID.
	 *
	 * @param String $uuid
	 *
	 * @return XenPool
	 */
	public function getPoolByUUID(String $uuid): XenPool
	{
		$xenResponse = $this->xenConnection->call('pool.get_by_uuid', [$uuid]);
		$refID       = $xenResponse;

		return new XenPool($this->xenConnection, $refID);
	}

	/**
	 * Returns the maximum number of host failures we could tolerate before we would be unable to restart the provided VMs
	 *
	 * @param array $config Map of protected VM reference to restart priority
	 *
	 * @return int              maximum value for ha_host_failures_to_tolerate given provided configuration
	 */
	public function haComputeHypotheticalMaxHostFailuresToTolerate(array $config): int
	{
		return $this->xenConnection->call('pool.ha_compute_hypothetical_max_host_failures_to_tolerate', [$config]);
	}

	/**
	 * Returns the maximum number of host failures we could tolerate before we would be unable to restart the provided VMs
	 *
	 * @return int            maximum value for ha_host_failures_to_tolerate given current configuration
	 */
	public function haComputeMaxHostFailuresToTolerate(): int
	{
		return $this->xenConnection->call('pool.ha_compute_max_host_failures_to_tolerate', []);
	}

	/**
	 * Return a VM failover plan assuming a given subset of hosts fail
	 *
	 * @param array $failed_hosts The array of hosts to assume have failed
	 * @param array $failed_VMs   The array of VMs to restart
	 *
	 * @return array                VM failover plan: a map of VM to host to restart the host on: [ ['vm' => XenVirtualMachine object, 'map' => [] ] ]
	 */
	public function haComputeVMFailoverPlan(array $failed_hosts, array $failed_VMs): array
	{
		$hostArr = array();
		$vmArr   = array();

		foreach ($failed_hosts as $host)
		{
			$hostArr[] = $host->getRefID();
		}

		foreach ($failed_VMs as $vm)
		{
			$vmArr[] = $vm->getRefID();
		}

		$failPlan = $this->xenConnection->call('pool.ha_compute_vm_failover_plan', [$failed_hosts, $failed_VMs]);
		$retArr   = array();
		foreach ($failPlan as $vm => $value)
		{
			$retArr[] = [
				'vm'  => new XenVirtualMachine($this->xenConnection, $vm),
				'map' => $value,
			];
		}

		return $retArr;
	}

	/**
	 * Returns true if a VM failover plan exists for up to 'n' host failures
	 *
	 * @param int $n The number of host failures to plan for
	 *
	 * @return bool
	 */
	public function haFailoverPlanExists(int $n): bool
	{
		return $this->xenConnection->call('pool.ha_failover_plan_exists', [$n]);
	}

	/**
	 * When this call returns the VM restart logic will not run for the requested number of seconds.
	 * If the argument is zero then the restart thread is immediately unblocked
	 *
	 * @param int $seconds
	 */
	public function haPreventRestartsFor(int $seconds)
	{
		$this->xenConnection->call('pool.ha_prevent_restarts_for', [$seconds]);
	}

	/**
	 * Initializes workload balancing monitoring on this pool with the specified wlb server
	 *
	 * @param string $wlb_url            The ip address and port to use when accessing the wlb server
	 * @param string $wlb_username       The username used to authenticate with the wlb server
	 * @param string $wlb_password       The password used to authenticate with the wlb server
	 * @param string $xenserver_username The username used by the wlb server to authenticate with the xenserver
	 * @param string $xenserver_password The password used by the wlb server to authenticate with the xenserver
	 */
	public function initializeWLB(string $wlb_url, string $wlb_username, string $wlb_password, string $xenserver_username, string $xenserver_password)
	{
		$this->xenConnection->call('pool.initialize_wlb', [$wlb_url, $wlb_username, $wlb_password, $xenserver_username, $xenserver_password]);
	}

	/**
	 * Instruct host to join a new pool
	 *
	 * @param string $master_address  The hostname of the master of the pool to join
	 * @param string $master_username The username of the master (for initial authentication)
	 * @param string $master_password The password for the master (for initial authentication)
	 */
	public function join(string $master_address, string $master_username, string $master_password)
	{
		$this->xenConnection->call('pool.join', [$master_address, $master_username, $master_password]);
	}

	/**
	 * Instruct host to join a new pool
	 *
	 * @param string $master_address  The hostname of the master of the pool to join
	 * @param string $master_username The username of the master (for initial authentication)
	 * @param string $master_password The password for the master (for initial authentication)
	 */
	public function joinForce(string $master_address, string $master_username, string $master_password)
	{
		$this->xenConnection->call('pool.join', [$master_address, $master_username, $master_password]);
	}

	/**
	 * Reconfigure the management network interface for all Hosts in the Pool
	 *
	 * @param XenNetwork $network The network
	 */
	public function managementReconfigure(XenNetwork $network)
	{
		$this->xenConnection->call('pool.management_reconfigure', [$network->getRefID()]);
	}

	/**
	 * Instruct a pool master, M, to try and contact its slaves and, if slaves are in emergency mode, reset their master address to M.
	 *
	 * @return array        list of hosts whose master address were successfully reset
	 */
	public function recoverSlaves(): array
	{
		$refIDs  = $this->xenConnection->call('pool.recover_slaves');
		$hostArr = array();

		foreach ($refIDs as $refID)
		{
			$hostArr[] = new XenHost($this->xenConnection, $refID);
		}

		return $hostArr;
	}

	/**
	 * Retrieves the pool optimization criteria from the workload balancing server
	 *
	 * @return array
	 */
	public function retrieveWLBConfiguration(): array
	{
		return $this->xenConnection->call('pool.retrieve_wlb_configuration');
	}

	/**
	 * Retrieves vm migrate recommendations for the pool from the workload balancing server
	 *
	 * @return array
	 */
	public function retrieveWLBRecommendations(): array
	{
		return $this->xenConnection->call('pool.retrieve_wlb_recommendations');
	}


	/**
	 * Send the given body to the given host and port, using HTTPS, and print the response.
	 * This is used for debugging the SSL layer.
	 *
	 * @param string $host
	 * @param int    $port
	 * @param string $body
	 *
	 * @return string
	 */
	public function sendTestPost(string $host, int $port, string $body): string
	{
		return $this->xenConnection->call('pool.send_test_post', [$host, $port, $body]);
	}

	/**
	 * Sets the pool optimization criteria for the workload balancing server
	 *
	 * @param array $config
	 */
	public function sendWLBConfiguration(array $config)
	{
		$this->xenConnection->call('pool.send_wlb_configuration', [$config]);
	}

	/**
	 * Forcibly synchronise the database now
	 */
	public function syncDatabase()
	{
		$this->xenConnection->call('pool.sync_database');
	}

}

?>