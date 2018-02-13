<?php
namespace Cisco;

/**
 * Description of Switch
 * TODO:
 * * Configure base interface (fastethernet, gigabitethernet, ...)
 * @author Glenn
 */
class SwitchL3Stackable extends Common {
    public function __construct($Hostname = 'router1.lan.local')
    {
        parent::__construct($Hostname);
        $this->addOpt('PortBase', 'GigabitEthernet', 'select:GigabitEthernet,FastEthernet', 'GigabitEthernet, FastEthernet, etc...', 'Switch');
        $this->addOpt('StackNo', '1', 'int', 'Stack nr of this switch (default is 1 when unstacked)', 'Switch');
        $this->addOpt('NROfPorts', 28, 'int', 'Number of switchports', 'Switch');
        $this->addOpt('MgmtIntf', 'Loopback0', 'string', 'Loopback interfaced used for management');
        $this->addOpt('MgmtIntfIPv4Addr', '192.168.0.2', 'string', 'Loopback interface IP Address');
        $this->addOpt('MgmtIntfIPv4Mask', '255.255.255.255', 'string', 'Subnet Mask');
        $this->addOpt('TimeZone', 'UTC', 'select:UTC,EST-EDT,CST-CDT,MST-MDT,PST-PDT', 'Time Zone / Summer Zone');

        $this->addOpt('RoutingProtcol', 'EIGRP', 'select:EIGRP,OSPFv2,OSPFv3', 'Routing Protocol', 'Routing');
        $this->addOpt('RoutingASIPv4Unicast', '1', 'string', 'Autonomous System', 'Routing');
        $this->addOpt('RoutingEIGRP', 'NamedMode', 'string', 'EIGRP Instance Name', 'Routing');
#        $NetworkOpts[] = new ConfigOption('RoutingNetworks', '10.20.30.40', 'string', 'Networks to be advertised', 'Routing');
#        $this->addOpt('RoutingNetworks', $NetworkOpts, 'listOfListOfOpts');

        $this->addOpt('VLANs', '1, 2', 'intarray', 'VLANs to create. Format: 1,2,10-20 (spaces allowed)', 'VLAN');
        $this->addOpt('AccessVLAN', 2, 'int', 'All access ports will be put in this VLAN', 'VLAN');
        $this->addOpt('TrunkPorts', 28, 'int', 'Ports to configure as trunk, format: 1,2,5-9 (spaces allowed)', 'VLAN');
        $this->addOpt('DHCPSnoopingTrustInterfaces', 28, 'int', 'Switchports to trust for DHCP, format: 1,2,5-9 (spaces allowed)', 'Protection');
        $this->addOpt('DisableVTP', true, 'bool');
        $this->addOpt('StormControlPPSLimit', 1000, 'int', 'Packets per second limit for storm-control. Shuts down interface when exceeded', 'Protection');
        $this->addOpt('EnableIPv6RAGuard', true, 'bool', "Enable IPv6 RAGuard and configure access ports to a Host profile (no RA's allowed)");
        // Override FQDNHostname here
        $this->addOpt('FQDNHostname', 'switch1.lan.local', 'string');
        
        $this->addOpt('AccessPortSTPProtection', 'bpduguard', 'select:bpduguard,bpdufilter', 'What to do when receiving STP PDUs. bpduguard: shutdown interface, bpdufilter: filter PDUs', 'Protection');
        $this->addOpt('IPDeviceTracking', true, 'bool', 'Enable IP device tracking on all ports', 'Protection');
        $this->addOpt('IPDeviceTrackingMax', 10, 'int', 'Maximum number of devices to track per port (range: 0 - 10)', 'Protection');
        $this->addOpt('IPDeviceTrackingProbeDelay', 10, 'int', 'Delay device tracking probe by this amount of seconds (range: 0 - 180)', 'Protection');
        $NTPOpts[] = new ConfigOption('IP', '10.20.30.40', 'string');
        $this->addOpt('NTPServers', $NTPOpts, 'listOfListOfOpts');

    }
    
    
    public function generate()
    {
        parent::generate();
        
        if($this->getOptVal('TimeZone') == 'EST-EDT') {
            $this->addLine('clock timezone EST -5');
            $this->addLine('clock summer-time EDT recurring');
        } elseif ($this->getOptVal('TimeZone') == 'CST-CDT'){
            $this->addLine('clock timezone CST -6');
            $this->addLine('clock summer-time CDT recurring');
        } elseif ($this->getOptVal('TimeZone') == 'MST-MDT'){
            $this->addLine('clock timezone MST -7');
            $this->addLine('clock summer-time MDT recurring');
        } elseif ($this->getOptVal('TimeZone') == 'PST-PDT'){
            $this->addLine('clock timezone PST -8');
            $this->addLine('clock summer-time PDT recurring');
        } elseif ($this->getOptVal('TimeZone') == 'UTC'){
            $this->addLine('clock timezone UTC 0');
        }
        
        if($this->getOptVal('EnableSSH')) {
            $this->EnableSSH(22, false);
        }
        
        if($this->getOptVal('EnableIPv6RAGuard')) {
            $Block = $this->addBlock('ipv6 nd raguard policy Host', ConfBlock::POS_BEGIN, true);
        }
        
        
        $this->addLine('ip dhcp snooping vlan 1-4094');
        $this->addLine('ip dhcp snooping');
        $this->addLine('no ip dhcp snooping information option');
        
        
        if($this->getOptVal('DisableVTP')) {
            $this->addLine('vtp mode transparent');
        }
        
        
        if($this->getOptVal('IPDeviceTracking')) {
            $this->addLine('ip device tracking');
            $this->addLine("ip device tracking probe delay {$this->getOptVal('IPDeviceTrackingProbeDelay')}");
        }
        
        
        /* Ports */
        $i = 1;
        while($i <= $this->getOptVal('NROfPorts')) {
            $IntBlock = $this->addBlock("interface {$this->getOptVal('PortBase')} {$this->getOptVal('StackNo')}/0/{$i}", ConfBlock::POS_INT);
            if(in_array($i, $this->parseNrFormat($this->getOptVal('TrunkPorts')))) {
                $IntBlock->addLine('switchport trunk encapsulation dot1q');
                $IntBlock->addLine('switchport mode trunk');
                if($this->getOptVal('DisableVTP')) {
                    $IntBlock->addLine('no vtp');
                }
            } else {
                $IntBlock->addLine("switchport mode access");
                $IntBlock->addLine("switchport nonegotiate");
                $IntBlock->addLine('spanning-tree portfast');
                $IntBlock->addLine("switchport access vlan {$this->getOptVal("AccessVLAN")}");
                $IntBlock->addLine('no vtp');
                $IntBlock->addLine('ip verify source');
                if($this->getOptVal('EnableIPv6RAGuard')) {
                    $IntBlock->addLine('ipv6 nd raguard attach-policy Host');
                }
                $IntBlock->addLine("storm-control broadcast level pps {$this->getOptVal("StormControlPPSLimit")}");
                $IntBlock->addLine('storm-control action shutdown');
                if($this->getOptVal('AccessPortSTPProtection') == 'bpduguard') {
                    $IntBlock->addLine('spanning-tree bpduguard enable');
                } elseif ($this->getOptVal('AccessPortSTPProtection') == 'bpdufilter'){
                    $IntBlock->addLine('spanning-tree bpdufilter enable');
                }
            }

            if(in_array($i, $this->parseNrFormat($this->getOptVal('DHCPSnoopingTrustInterfaces')))) {
                $IntBlock->addLine('ip dhcp snooping trust');
            }
            
            if($this->getOptVal('IPDeviceTracking')) {
                $IntBlock->addLine("ip device tracking maximum {$this->getOptVal('IPDeviceTrackingMax')}");
            }
            
            $i++;
        }
        
        $IntBlock = $this->addBlock("interface {$this->getOptVal('MgmtIntf')}", ConfBlock::POS_INT);
        $IntBlock->addLine('no shutdown');
        $IntBlock->addLine("ip address {$this->getOptVal('MgmtIntfIPv4Addr')} {$this->getOptVal('MgmtIntfIPv4Mask')}");
        $IntBlock->addLine('no ip unreachables');
        $IntBlock->addLine('no ip proxy-arp');
        $IntBlock->addLine('no ip redirects');
        
        
        if(count($this->getOptVal('NTPServers')['IP']) > 0)
        {
            foreach ($this->getOptVal('NTPServers')['IP'] as $ntpServer)
            {
                if($ntpServer === '')
                {
                    continue;
                }
                if($NTPBlock === null)
                {
                    $NTPBlock = $this->addBlock("ntp server {$ntpServer}", ConfBlock::POS_NTP, true);
                }
                else
                {
                    $NTPBlock->addLine("ntp server {$ntpServer}");
                }
            }
        }
    }
}
