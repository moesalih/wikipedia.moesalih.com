<?php
/* Copyright (C) 2010. Troy Hirni. All rights reserved.
  This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	 This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	 You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.. 
*/


/*
** Version 017 - Beta.
** Please see http://pxtreme.sf.net for documentation, examples, and news.
*/


// ----------------------------------------------------------------
// PX BASE - Base class for px, px_nodes, and px_method
// - Gives access to:
//    * the owning px object
//    * the current list of nodes
//    * all available px methods
//    * the "to string" functions
//    * static Path, Version, and Error methods
//    * utilities for method implementation
// ----------------------------------------------------------------
abstract class pxObject
{
	static public $PXFilePath;
	static public $Default;
	
	//
	// VERSION
	//
	static public function Version() {return "017";}
	
	
	//
	// ABSTRACT - Implemented by px and px_method classes
	//
	abstract public function px();
	abstract public function nodes();
	
	
	//
	// UTILITY
	//
	
	// DOC
	public function doc () {
		switch (sizeof($ar=func_get_args())) {
			case 0: return $this->px()->doc; break;
			case 1: return $this->px()->doc->$ar[0]; break;
			default:
				$this->px()->doc->$ar[0] = $ar[1];
				return $this;
				break;	
		}	
	}
	
	// GET
	// - Return a DOMNode at index $n (or all, as an array)
	public function get ($n=NULL) {
		return $n!==NULL ? $this->nodes()->item($n) : $this->_nodeArray();
	}
	
	// INDEX
	// - returns the $Nth item in the node-list
	public function index($n) {
		return new px_nodes($this->px(), $this->nodes()->item($n));
	}
	
	// FN - Wraps functions (because they seem to be strings)
	static public function fn ($str) {return new px_function($str);}
	
	// AS FUNCTION
	static public function asFunction($p, $def=NULL) {
		return is_a($p, "Closure") ? new px_function($p) : (is_a($p, "px_function") ? $p : $def);
	}
	
	
	//
	// 'MAGIC' METHODS
	//
	
	// TO STRING
	public function __toString () {return $this->toString();}
	public function toString ($options=0) {
		$str="";
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++)
			$str .= $this->px()->doc->saveXml($nl->item($i), $options);
		return $str;
	}
	
	// CALL - Object Methods
	// - What's called as px methods are actually classes with an exec() method.
	// - Methods can be defined anywhere. If undefined (before being called), 
	//   look in the 'plugin' folder, then (if found) instantiate and return.
	public function __call($name, $arguments)
	{
		$cn = "px_method_$name";
		if (!class_exists($cn)) {
			include_once($p=self::$PXFilePath."/plugin/{$name}/{$name}.php");
			if (!class_exists($cn))
				die("px Method '$name' is not defined.<br>$p");
			if (!is_a($c=new $cn($this->px(), $this->nodes()), "px_method"))
				die("Class '$cn' must extend px_method.");
		}
		else
			$c=new $cn($this->px(),$this->nodes());
		$v = isset($arguments)?$c->exec($arguments):$c->exec();
		return isset($v)?$v:$c;
	}
	
	
	//
	// PATH
	// - PARAMETER: A String - a file path.
	// - Reduce the path (for cross-platform, cross-version compatability)
	// - Removes all ".." and "."
	// - This is mostly centered around construction of a file path that
	//   will be appropriate for any function on any PHP system.
	//
	static public function Path ($path) {
		if (!$path=trim($path))
			return "";
		$ar=mb_split("[\\/]", $path);
		if (!isset($ar))
			return;
		$arResult=array();
		foreach ($ar as $itm) {
			switch($itm) {
				case '..':
					if (!array_pop($arResult))
						return NULL;
					break;
				case '.': break;
				case '': break;
				default:
					array_push($arResult,$itm);
					break;
			}
		}
		return implode('/',$arResult);
	}
	
	
	//
	// DOM MANIPULATION
	//
	
	// XML MAKE DOC
	static public function XmlMakeDoc ($xml, $options=NULL) {
		if (!$xml)
			return self::XmlNewDoc($options);	
		
		// NOTE: This method makes a COPY of a dom-document argument!
		if (is_a($xml, "DOMDocument"))
			return self::XmlMakeDoc($xml->firstChild, $options); 
		
		$doc = self::XmlNewDoc($options);	
		if (is_string($xml)) {
			if (mb_strpos($xml, "<")!==FALSE)
				$doc->loadXml($xml);
			else
				$doc->load(self::Path($xml));
		}
		else if (is_a($xml, "pxObject"))
			$doc->appendChild($xml->px()->doc->firstChild);
		else if (is_a($xml, "DOMNode"))
			$doc->appendChild($xml);
		return $doc;
	}
	
	// XML NEW DOC (create with default params)
	static public function XmlNewDoc ($options=NULL) {
		$doc = new DOMDocument();
		foreach (($options?$options:self::$Default) as $name => $val)
			$doc->$name = $val;
		return $doc;
	}

	
	// SET XML
	// - sets the xml for whatever pxObject $this is
	// - pass xml text, dom object, or xml-returning function
	protected function _setXml($in) {
		$nl = $this->nodes();
		if ((!$nl->length) || ($this->doc()->firstChild===$this->get(0))) {
			$this->px()->doc()->loadXml($in); // this is the px - load the entire xml
			$this->px()->nodes = $this->px()->doc()->firstChild;
			$this->nodes = $this->px()->nodes();
			return $this;
		}
		$this->_fAppend(new px_fragment($in));
	}
	
	protected function _fAppend($frag) {
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++) {
			$newNodes=$frag->get($this,$i);
			if (isset($newNodes)) {
				$e = $nl->item($i);
				$this->_empty($e);
				for ($j=0; $j<$newNodes->length; $j++) {
					$e->appendChild($e->ownerDocument->importNode($newNodes->item($j),1));
				}
			}
		}
	}
	
	protected function _nodeArray($e) {
		$ar = array();
		$nl=$this->nodes();
		for($i=0; $i<$nl->length; $i++)
			$ar[] = $nl->item($i);
		return $ar;
	}
	
	protected function _empty($e) {
		$ar=array();
		if ($nl=$e->childNodes) {
			for($i=$nl->length; --$i >= 0;)
				$ar[]=$e->removeChild($nl->item($i));
		}
		return $ar;
	}
	
	protected function _remove($e) {
		if ($e->parentNode)
			return $e->parentNode->removeChild($e);
	}
	
	// WRAP - $p must be a px_value_dom
	protected function _wrap($e, $p, $i) {
		$wrapMe=$this->px()->doc->importNode($p->get($this, $i)->firstChild,0);
		$node=$e->parentNode->insertBefore($wrapMe,$e);
		return $node->appendChild($e);
	}
	
	
	//
	// ATTRIBUTE/CLASS MANIPULATION
	// - $e is always an element
	// - $val is always a string
	//
	
	protected function _css($e, $sel=NULL, $val=NULL) {
		$css=array();
		foreach(mb_split(";", $e->getAttribute("style")) as $section) {
			if (sizeof($ar = mb_split(":", $section))==2)
				$css[trim($ar[0])] = trim($ar[1]);
		}
		if ($sel===NULL) return $css;
		if ($val===NULL) return isset($css[$sel]) ? $css[$sel] : "";
		$style="";
		$css[$sel]=$val;
		foreach($css as $n => $v)
			$style .= "$n:$v;";
		$e->setAttribute("style", $style);
	}
	
	protected function _addclass($e, $val) {
		$sCur = trim($e->getAttribute("class"));
		$aCur = $sCur ? mb_split(" ", $sCur) : array();
		$aCur = array_merge($aCur, mb_split(" ", $val));
		$sSet = implode(" ", array_unique($aCur));
		$e->setAttribute("class", $sSet);
	}
	
	protected function _rmvclass($e, $val)
	{
		$arClassesToRemove = mb_split(" ", $val);
		$sOldClassList = $e->getAttribute("class");
		$aNewClassList = array();
		foreach (mb_split(" ", $sOldClassList) as $sClassName) {
			if (!in_array($sClassName, $arClassesToRemove))
				$aNewClassList[] = $sClassName;
		}
		if ($sNewClassList=implode(" ",$aNewClassList))
			$e->setAttribute("class", $sNewClassList);
		else
			$e->removeAttribute("class");
	}
	
	protected function _toggleclass($e, $val) {
		$ar=mb_split(" ",$e->getAttribute("class"));
		foreach(mb_split(" ", $val) as $v) {
			if (in_array($v, $ar))
				$this->_rmvclass($e, $v);
			else
				$this->_addclass($e, $v);
		}
	}
}

pxObject::$PXFilePath = '/'.pxObject::Path(__FILE__."/..");
pxObject::$Default = array('recover'=>TRUE, 'preserveWhiteSpace'=>FALSE, 'formatOutput'=>TRUE);




// ----------------------------------------------------------------
// PX
// - Represents one xml document and it's full set of nodes
// ----------------------------------------------------------------
class px extends pxObject
{
	public $doc=NULL;
	
	// CONSTRUCT
	// - pass a DOM document, node, or element...
	//   OR a string representing a file path or xml
	public function __construct($xml=NULL) {
		$this->doc = is_a($xml, "DOMDocument") ? $xml : self::XmlMakeDoc($xml);
	}
	
	// PX, NODES - Abstract Implementations
	public function px() {return $this;}
	
	// NODES - a PHP DOMNodeList (or equivalent)
	public function nodes() {return $this->doc->childNodes;}
}




// ----------------------------------------------------------------
// PX NODES
// - Serves as a parameter to a function representing one or more
//   nodes and can also call all px methods on itself.
// ----------------------------------------------------------------
class px_nodes extends pxObject
{
	public function px() {return $this->px;}
	public function nodes() {return $this->nodes;}
	function __construct($px, $inNodes) {
		$this->px = $px;
		if (is_a($inNodes,"px_nodes"))
			$this->nodes=$inNodes->nodes();
		elseif (is_a($inNodes,"DOMNodeList") || is_a($inNodes,"px_item_list"))
			$this->nodes = $inNodes;
		else
			$this->nodes = new px_item_list ($inNodes);
	}
}




// ----------------------------------------------------------------
// PX METHOD
// ----------------------------------------------------------------
abstract class px_method extends pxObject
{
	public function px() {return $this->px;}
	public function nodes() {return $this->nodes;}
	
	final public function __construct($px, $nodes) {
		$this->px=$px;
		$this->nodes=$nodes;
	}
	
	// EXEC
	// - $arParams is whatever the caller passed. for example... 
	//   	px().myFn("param1","param2");
	//   ...$arParams arives at the methods/myFn.php exec() method as
	//   	array("param1","param2");
	abstract public function exec($arParams);
}


// ----------------------------------------------------------------
// PX METHODS - Built-In Methods
// - These methods are defined here so that so that they're always
//   available only by including the file. They can't be replaced.
// ----------------------------------------------------------------

// XPath
class px_method_xpath extends px_method {
	public function exec ($ar) {
		if (isset($ar[0]) && ($ar[0]!=="")) {
			$xp = new DOMXPath ($this->px()->doc);
			$this->nodes = isset($ar[1]) ? $xp->query($ar[0],$ar[1]) : $xp->query($ar[0]);
		}
	}
}

// FIND
class px_method_find extends px_method {
	public function exec ($ar) {
		if (isset($ar[0]) && ($ar[0]!=="")) {
			$nl = $this->nodes();
			$rr = array();
			for($i=0; $i<$nl->length; $i++) {
				$xp = new DOMXPath ($this->px()->doc);
				$rnl = $xp->query($ar[0], $nl->item($i));
				for ($j=0; $j<$rnl->length; $j++)
					$rr[] = $rnl->item($j);
			}
			$this->nodes = new px_item_list($rr);
		}
	}
}

// PARENT
class px_method_parent extends px_method {
	public function exec ($ar) {
		$rr = array();
		$nl = $this->nodes();
		$bCK = (isSet($ar[0]) && (($ck=$ar[0])!=="")) ? TRUE : FALSE;
		for($i=0; $i<$nl->length; $i++) {
			$par=(($cur=$nl->item($i))?$cur->parentNode:NULL);
			if ($par && (array_search($par, $rr, TRUE)===FALSE)) {
				if (!$bCK)
					$rr[]=$par;
				else {
					$xp = new DOMXPath ($this->px()->doc);
					$rnl = $xp->query($ar[0], $par->parentNode?$par->parentNode:$par);
					for($j=0; $j<$nl->length; $j++) {
						if ($par === $rnl->item($j)) {
							$rr[]=$par;
							break;
						}
					}
				}
			}
		}
		$this->nodes = new px_item_list($rr);
	}
}

// CHILDREN
class px_method_children extends px_method {
	public function exec ($ar) {
		$rr = array();
		$nl = $this->nodes();
		if (isSet($ar[0]) && ($ar[0]!=="")) {
			for($i=0; $i<$nl->length; $i++) {
				$match=$nl->item($i);
				$xp = new DOMXPath ($this->px()->doc);
				$rnl = $xp->query($ar[0], $match);
				for ($j=0; $j<$rnl->length; $j++) {
					if (($node=$rnl->item($j)) && ($node->parentNode===$match))
						$rr[] = $node;
				}
			}
		}
		else {
			for($i=0; $i<$nl->length; $i++) {
				$nn = $nl->item($i);
				$nc = $nn->childNodes;
				for ($j=0; $j<$nc->length; $j++)
					$rr[] = $nc->item($j);
			}
		}
		$this->nodes = new px_item_list($rr);
	}
}

// FIRST
class px_method_first extends px_method {
	public function exec ($ar) {
		$this->nodes = new px_item_list(array($this->nodes()->item(0)));
	}
}

// XML
class px_method_xml extends px_method {
	public function exec ($ar) {
		switch (sizeof($ar)) {
			case 0:
				return $this->toString(0);
				break;
			case 1:
				$this->_setXml($ar[0]);
				break;
		}
	}
}

// HTML
class px_method_html extends px_method {
	public function exec ($ar) {
		switch (sizeof($ar)) {
			case 0:
				return $this->toString(LIBXML_NOEMPTYTAG);
				break;
			case 1:
				$this->_setXml($ar[0]);
				break;
		}
	}
}

// TEXT
class px_method_text extends px_method {
	public function exec ($ar) {
		$nl = $this->nodes();
		switch (sizeof($ar)) {
			case 0:
				$str="";
				for($i=0; $i<$nl->length; $i++)
					$str .= $nl->item($i)->textContent;
				return $str;
				break;
			case 1:
				$fn = self::asFunction($ar[0]);
				for($i=0; $i<$nl->length; $i++) {
					$n = $nl->item($i);
					$val = $fn ? $fn->get($this, $i) : $ar[0];
					if (isset($val)) {
						$this->_empty($n);
						$n->appendChild(new DOMText($val));
					}
				}
				break;
		}
	}
}

// VAL
class px_method_val extends px_method {
	public function exec ($ar) {
		switch (sizeof($ar)) {
			case 0:
				if ($n=$this->nodes()->item(0))
					return $n->hasAttribute("value")?$n->getAttribute("value"):$n->textContent;
				break;
			case 1:
				$nl = $this->nodes();
				for($i=0; $i<$nl->length; $i++) {
					$n = $nl->item($i);
					$val = ($f=self::asFunction($ar[0])) ? $f->get($this, $i) : $ar[0];
					$n->setAttribute("value", $val);
				}
				break;
		}
	}
}

// EACH
// - Execute a function for all currently selected nodes.
// - This is the only method that can take a string value as function name
//   and actually execute it as a function. The rest take strings as a value,
//   rather than a function.
class px_method_each extends px_method {
	public function exec ($ar) {
		if (!($fn = $this->asFunction($ar[0])))
			$fn = new px_function($ar[0]);
		$nl = $this->nodes();
		for ($i=0; $i<$nl->length; $i++) {
			$fn->get($this, $i);
		}
	}
}

// ATTR
// - With 1 parameter (a string) it returns the value of the first 
//   matching nodes attribute with name matching the parameter.
// - With 2 parameters (strings), you set the attribute (named by
//   the first param) for ALL matching nodes to the value specified
//   by the second parameter.
class px_method_attr extends px_method {
	public function exec ($ar) {
		if (!isset($ar[1]))
			return ($node=$this->nodes()->item(0)) ? $node->getAttribute($ar[0]) : "";
		$nl = $this->nodes();
		$fn = self::asFunction($ar[1]);
		for($i=0; $i<$nl->length; $i++) {
			$val = $fn ? $fn->get($this, $i) : $ar[1];
			$nl->item($i)->setAttribute($ar[0], $val);
		}
	}
}

// REMOVE ATTR
class px_method_removeAttr extends px_method {
	public function exec ($ar) {
		$nl = $this->nodes();
		$fn = self::asFunction($ar[0]);
		for($i=0; $i<$nl->length; $i++) {
			$val = $fn ? $fn->get($this, $i) : $ar[0];
			$nl->item($i)->removeAttribute($val);
		}
	}
}

// ADD CLASS
class px_method_addClass extends px_method {
	public function exec ($ar) {
		$nl = $this->nodes();
		$fn = self::asFunction($ar[0]);
		for($i=0; $i<$nl->length; $i++) {
			$val = $fn ? $fn->get($this, $i) : $ar[0];
			if (isset($val))
				$this->_addclass($nl->item($i), $val);
		}
	}
}

// REMOVE CLASS
class px_method_removeClass extends px_method {
	public function exec ($ar) {
		$nl = $this->nodes();
		$fn = self::asFunction($ar[0]);
		for($i=0; $i<$nl->length; $i++)
			$this->_rmvclass($nl->item($i), $fn ? $fn->get($this, $i) : $ar[0]);
	}
}

// HAS CLASS
class px_method_hasClass extends px_method {
	public function exec ($ar) {
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++) {
			if (in_array($ar[0], mb_split(" ",$nl->item($i)->getAttribute("class"))))
				return 1;
		}
		return 0;
	}
}

// TOGGLE CLASS
class px_method_toggleClass extends px_method {
	public function exec ($ar) {
		$nl = $this->nodes();
		$fn = self::asFunction($ar[0]);
		for($i=0; $i<$nl->length; $i++) {
			$val = $fn ? $fn->get($this, $i) : $ar[0];
			if (!isset($ar[1]))
				$this->_toggleclass($nl->item($i), $val);
			else if ($ar[1])
				$this->_addClass($nl->item($i), $val);
			else
				$this->_rmvclass($nl->item($i), $val);
		}
	}
}

// CSS
class px_method_css extends px_method {
	public function exec ($ar) {
		$nl = $this->nodes();
		if (!isset($ar[1]))
			return $this->_css($nl->item(0), $ar[0]);
		$fn = self::asFunction($ar[1]);
		for($i=0; $i<$nl->length; $i++) {
			$val = $fn ? $fn->get($this, $i) : $ar[1];
			$this->_css($nl->item($i), $ar[0], $val);
		}
	}
}

// AFTER
class px_method_after extends px_method {
	public function exec ($ar) {
		$frag = new px_fragment($ar[0]);
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++) {
			$newNodes=$frag->get($this,$i);
			if (isset($newNodes)) {
				$e = $nl->item($i);
				for($j=0; $j<$newNodes->length; $j++) {
					$nn = $e->ownerDocument->importNode($newNodes->item($j),1);
					$e = $e->nextSibling ? $e->parentNode->insertBefore($nn, $e->nextSibling) : $e->parentNode->appendChild($nn);
				}
			}
		}
	}
}

// APPEND
class px_method_append extends px_method {
	public function exec ($ar) {
		$frag = new px_fragment($ar[0]);
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++) {
			$newNodes=$frag->get($this,$i);
			if (isset($newNodes)) {
				$e = $nl->item($i);
				for($j=0; $j<$newNodes->length; $j++) {
					$n = $e->ownerDocument->importNode($newNodes->item($j),1);
					$e->appendChild($n);
				}
			}
		}
	}
}

// BEFORE
class px_method_before extends px_method {
	public function exec ($ar) {
		$frag = new px_fragment($ar[0]);
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++) {
			$newNodes=$frag->get($this,$i);
			if (isset($newNodes)) {
				$e = $nl->item($i);
				for($j=0; $j<$newNodes->length; $j++) {
					$n = $e->ownerDocument->importNode($newNodes->item($j),1);
					$e->parentNode->insertBefore($n, $e);
				}
			}
		}
	}
}

// PREPEND
class px_method_prepend extends px_method {
	public function exec ($ar) {
		$frag = new px_fragment($ar[0]);
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++) {
			$newNodes=$frag->get($this,$i);
			if (isset($newNodes)) {
				$p = $nl->item($i);
				$e= $p->firstChild;
				for($j=0; $j<$newNodes->length; $j++) {
					$n = $p->ownerDocument->importNode($newNodes->item($j),1);
					$x = $e ? $p->insertBefore($n, $e) : $p->appendChild($n);
				}
			}
		}
	}
}


// CLONE
class px_method_clone extends px_method {
	public function exec ($arIgnored) {
		$ar = array();
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++)
			$ar[] = $nl->item($i)->cloneNode(1);
		return new px_nodes ($this->px(), $ar);
	}
}

// DETACH
class px_method_detach extends px_method {
	public function exec ($ar) {
		$nl = $this->nodes();
		$ar = array();
		for($i=0; $i<$nl->length; $i++)
			$ar[] = $this->_remove($nl->item($i),0);
		$this->nodes = new px_item_list($ar);
	}
}

// EMPTY
class px_method_empty extends px_method {
	public function exec ($ar) {
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++)
			$this->_empty($nl->item($i));
	}
}

// REMOVE
class px_method_remove extends px_method {
	public function exec ($ar) {
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++)
			$this->_remove($nl->item($i),0);
	}
}

// WRAP
class px_method_wrap extends px_method {
	public function exec ($ar) {
		$dom=new px_value_dom($ar[0]);
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++)
			$this->_wrap($nl->item($i), $dom, $i);
	}
}

// WRAP INNER
class px_method_wrapInner extends px_method {
	public function exec ($ar) {
		$dom=new px_value_dom($ar[0]);
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++) {
			$node=$nl->item($i);
			$wrapper=$node->ownerDocument->importNode($dom->get($this,$i)->firstChild,1);
			if ($nnl = $node->childNodes) {
				while($nnn=$nnl->item(0))
					$wrapper->appendChild($nnn);
			}
			$node->appendChild($wrapper);
		}
	}
}

// REPLACE WITH
// - pass xml (or function generating xml) that will replace currently matched nodes
class px_method_replaceWith extends px_method {
	public function exec ($ar) {
		$dom=new px_value_dom($ar[0]);
		$nl = $this->nodes();
		for($i=0; $i<$nl->length; $i++) {
			$e = $nl->item($i);
			$e->parentNode->replaceChild($e->ownerDocument->importNode($dom->get($this,$i)->firstChild,1), $e);
		}
	}
}

// REPLACE ALL
// - currently matched nodes will replace the nodex specified by nodes that match the
//   xpath in the input argument.
class px_method_replaceAll extends px_method {
	public function exec ($ar) {
		$replaceUs = $this->nodes();
		$withUs = $this->xpath($ar[0])->nodes();//same as {this->xpath($ar[0]); this->nodes();}
		$nWithUs = new px_nodes($this->px(), $withUs);
		for($i=$replaceUs->length; --$i >= 0; ) {// walking backward through the replacERs
			$replaceMe = $replaceUs->item($i); // replace the last target
			if ($withMe=$withUs->item($withUs->length-1)) {
				$withMe = $withMe->cloneNode(1);
				$replaceMe->parentNode->replaceChild($node=$replaceMe->ownerDocument->importNode($withMe,1),$replaceMe);
			}
			for($j=$withUs->length; --$j >= 1; ) {
				$withMe = $withUs->item($j);
				$withMe = $withMe->cloneNode(1);
				$node = $node->parentNode->insertBefore($replaceMe->ownerDocument->importNode($withMe,1), $node);
			}
		}
		$nWithUs = new px_nodes($this->px(), $withUs);
		$nWithUs->remove();
	}
}



// APPEND TO
// - append $this->nodes() to whatever the given xpath is (from $ar[0])
class px_method_appendTo extends px_method {
	public function exec ($ar) {
		$copyUs = $this->nodes();
		$toHere = $this->xpath($ar[0]);
		$nMoveUs = new px_nodes($this->px(), $copyUs);
		$toNodes = $toHere->nodes();
		$nl = $nMoveUs->nodes();
		$len = $nl->length;
		for ($i=0; $i<$len; $i++) {
			for($j=0; $j<$toNodes->length; $j++) {
				$n = $toNodes->item($j);
				$n->appendChild($n->ownerDocument->importNode($nl->item($i)->cloneNode(1),1));
			}
		}
		$nMoveUs->remove();
	}
}

// INSERT AFTER
class px_method_insertAfter extends px_method {
	public function exec ($ar) {
		$copyUs = $this->nodes();
		$afterUs = $this->xpath($ar[0]);
		$nMoveUs = new px_nodes($this->px(), $copyUs);
		$afterNodes = $afterUs->nodes();
		$nl = $nMoveUs->nodes();
		$len = $nl->length;
		for ($i=0; $i<$len; $i++) {
			for($j=0; $j<$afterNodes->length; $j++) {
				$moveMe = $nl->item($i)->cloneNode(1);
				$toHere = $afterNodes->item($j);
				if ($toHere->nextSibling)
					$toHere->parentNode->insertBefore($toHere->ownerDocument->importNode($moveMe,1),$toHere->nextSibling);
				else
					$toHere->parentNode->appendChild($toHere->ownerDocument->importNode($moveMe,1));
			}
		}
		$nMoveUs->remove();
	}
}

// INSERT BEFORE
class px_method_insertBefore extends px_method {
	public function exec ($ar) {
		$copyUs = $this->nodes();
		$b4here = $this->xpath($ar[0]);
		$nMoveUs = new px_nodes($this->px(), $copyUs);
		$b4nodes = $b4here->nodes();
		$nl = $nMoveUs->nodes();
		$len = $nl->length;
		for ($i=0; $i<$len; $i++) {
			for($j=0; $j<$b4nodes->length; $j++) {
				$moveMe = $nl->item($i)->cloneNode(1);
				$toHere = $b4nodes->item($j);
				$toHere->parentNode->insertBefore($toHere->ownerDocument->importNode($moveMe,1),$toHere);
			}
		}
		$nMoveUs->remove();
	}
}




// ----------------------------------------------------------------
// UTILITY
// ----------------------------------------------------------------

class px_value {
	protected $val;
	public function __construct($val=NULL) {$this->val = $val;}
	public function set($val) {$this->val=$val;}
	public function get() {return $this->val;}
}


class px_function extends px_value
{
	public function get() {
		$args = func_get_args();
		return call_user_func_array($this->val, $args);
	}
	public function call($ar) {
		return isset($ar) ? call_user_func_array($this->val, $ar) : call_user_func($this->val);
	}
}

// DOM
// - used by px_method subclasses - represents a dom object
class px_value_dom extends px_value
{
	var $fn = NULL;
	public function __construct($in) {
		$this->set($in);
	}
	
	public function set($in) {
		if (!($this->fn = px::asFunction($in)))
			$this->val = px::XmlMakeDoc($in);
	}
	
	public function get($args) {
		if (!$this->fn)
			return $this->val;
		$xml = isset($args) ? $this->fn->call(func_get_args()) : $this->fn->get();
		return $this->val = px::XmlMakeDoc($xml);
	}
}

// FRAGMENT
// - deals with a fragment of xml (typically a parameter to some method)
class px_fragment extends px_value
{
	var $fn = NULL;
	public function __construct($in) {
		$this->set($in);
	}
	
	public function set($in) {
		if ($fn=px::asFunction($in))
			$this->fn = $fn;
		else {
			$this->val = px::XmlMakeDoc("<PX>$in</PX>", array('preserveWhiteSpace'=>1));
		}
	}
	
	public function get($args) {
		if ($this->fn) {
			$xml = isset($args) ? $this->fn->call(func_get_args()) : $this->fn->get();
			if (!isset($xml))
				return;
			$this->val = px::XmlMakeDoc("<PX>$xml</PX>");
		}
		return $this->val->firstChild->childNodes;
	}
}


// ITEM LIST
// - This fakes a DOMNodeList.
// - Pass in an array of nodes OR just nodes.
class px_item_list {
	function __construct ($nodes) {
		$this->nodes = is_array($nodes) ? $nodes : func_get_args();
		$this->length = sizeof($this->nodes);
	}
	function item ($i) {
		return $this->nodes[$i];
	}
}


// PX - a shortcut for "new px($arg)"
function px($arg=NULL) {return new px($arg);}

?>
