<?php

// Parse a Newick tree 

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/node.php');
require_once(dirname(__FILE__) . '/tree.php');
require_once(dirname(__FILE__) . '/node_iterator.php');

define('NEXUSPunctuation', "()[]{}/\\,;:=*'\"`+-");
define('NEXUSWhiteSpace', "\n\r\t ");

//----------------------------------------------------------------------------------------
class TokenTypes
{
	const None 			= 0;
	const String 		= 1;
	const Hash 			= 2;
	const Number 		= 3;
	const SemiColon 	= 4;
	const OpenPar		= 5;
	const ClosePar 		= 6;
	const Equals 		= 7;
	const Space 		= 8;
	const Comma  		= 9;
	const Asterix 		= 10;
	const Colon 		= 11;
	const Other 		= 12;
	const Bad 			= 13;
	const Minus 		= 14;
	const DoubleQuote 	= 15;
	const Period 		= 16;
	const BackSlash 	= 17;
	const QuotedString	= 18;
}

//----------------------------------------------------------------------------------------
class NumberTokens
{
	const start 		= 0;
	const sign 			= 1;
	const digit 		= 2;
	const fraction 		= 3;
	const expsymbol 	= 4;
	const expsign 		= 5;
	const exponent 		= 6;
	const bad 			= 7;
	const done 			= 8;
}

//----------------------------------------------------------------------------------------
class StringTokens
{
	const ok 			= 0;
	const quote 		= 1;
	const done 			= 2;
}

//----------------------------------------------------------------------------------------
class NexusError
{
	const ok 			= 0;
	const nobegin 		= 1;
	const noend 		= 2;
	const syntax 		= 3;
	const badcommand 	= 4;
	const noblockname 	= 5;
	const badblock	 	= 6;
	const nosemicolon	= 7;
}

//----------------------------------------------------------------------------------------
class Scanner
{
	public $error = 0;
	public $comment = '';
	public $pos = 0;
	public $str = '';
	public $token = TokenTypes::None;
	public $buffer = '';
	
	//------------------------------------------------------------------------------------
	function __construct($str)
	{
		$this->str = $str;
	}

	//------------------------------------------------------------------------------------
	function GetToken($returnspace = false)
	{		
		$this->token = TokenTypes::None;
		while (($this->token == TokenTypes::None) && ($this->pos < strlen($this->str)))
		{
			//echo "[" . __LINE__ . "] +" . $this->str{$this->pos} . "\n";
			if (strchr(NEXUSWhiteSpace, substr($this->str, $this->pos, 1)))
			{
				if ($returnspace && (substr($this->str, $this->pos, 1) == ' '))
				{
					$this->token = TokenTypes::Space;
				}
			}
			else
			{
				if (strchr (NEXUSPunctuation, substr($this->str, $this->pos, 1)))
				{
					$this->buffer = substr($this->str, $this->pos, 1);
					//echo "[" . __LINE__ . "] -" . $this->str{$this->pos} . "\n";
 					switch (substr($this->str, $this->pos, 1))
 					{
 						case '[':
 							$this->ParseComment();
 							break;
 						case "'":
 							if ($this->ParseString())
 							{
 								$this->token = TokenTypes::QuotedString;
 							}
 							else
 							{
 								$this->token = TokenTypes::Bad;
 							}
 							break;
						case '(':
							$this->token = TokenTypes::OpenPar;
							break;
						case ')':
							$this->token = TokenTypes::ClosePar;
							break;
						case '=':
							$this->token = TokenTypes::Equals;
							break;
						case ';':
							$this->token = TokenTypes::SemiColon;
							break;
						case ',':
							$this->token = TokenTypes::Comma;
							break;
						case '*':
							$this->token = TokenTypes::Asterix;
							break;
						case ':':
							$this->token = TokenTypes::Colon;
							break;
						case '-':
							$this->token = TokenTypes::Minus;
							break;
						case '"':
							$this->token = TokenTypes::DoubleQuote;
							break;
					   	case '/':
							$this->token = TokenTypes::BackSlash;
							break;
						default:
							$this->token = TokenTypes::Other;
							break;
					}
				}
				else
				{
					if (substr($this->str, $this->pos, 1) == '#')
					{
						$this->token = TokenTypes::Hash;

					}
					else if (substr($this->str, $this->pos, 1) == '.')
					{
						$this->token = TokenTypes::Period;
					}
					else
					{
						if (is_numeric(substr($this->str, $this->pos, 1)))
						{
							if ($this->ParseNumber())
							{
								$this->token = TokenTypes::Number;
							}
							else
							{
								$this->token = TokenTypes::Bad;
							}
						}
						else
						{
							if ($this->ParseToken())
							{
								$this->token = TokenTypes::String;
							}
							else
							{
								$this->token = TokenTypes::Bad;
							}
						}
					}
				}
			}
			$this->pos++;			

		}
		return $this->token;
	}
	
	
	//------------------------------------------------------------------------------------
	function ParseComment()
	{
		$this->buffer = '';
		
		while ((substr($this->str, $this->pos, 1) != ']') && ($this->pos < strlen($this->str)))
		{
			$this->buffer .= substr($this->str, $this->pos, 1);
			$this->pos++;
		}
		$this->buffer .= substr($this->str, $this->pos, 1);
	}

	//------------------------------------------------------------------------------------
	function ParseNumber()
	{
		$this->buffer = '';
		$state = NumberTokens::start;
		
		while (
			($this->pos < strlen($this->str))
			//&& (!strchr (NEXUSWhiteSpace, $this->str{$this->pos}))
			//&& (!strchr (NEXUSPunctuation, $this->str{$this->pos}))
			//&& ($this->str{$this->pos} != '-')
			&& ($state != NumberTokens::bad)
			&& ($state != NumberTokens::done)
			)
		{
			//echo $state . ' ' . $this->pos . ' ' . $this->str{$this->pos} . "\n";
				
			if (is_numeric(substr($this->str, $this->pos, 1)))
			{
				//echo "[" . __LINE__ . "] number\n";
				switch ($state)
				{
					case NumberTokens::start:
					case NumberTokens::sign:
						$state =  NumberTokens::digit;
						break;
					case NumberTokens::expsymbol:
					case NumberTokens::expsign:
						$state =  NumberTokens::exponent;
						break;
					default:
						break;
				}
			}
			else if ((substr($this->str, $this->pos, 1) == '-') || (substr($this->str, $this->pos, 1) == '+'))
			{
				//echo "[" . __LINE__ . "] -|+\n";
				switch ($state)
				{
					case NumberTokens::start:
						$state = NumberTokens::sign;
						break;
					case NumberTokens::digit:
						$state = NumberTokens::done;
						break;
					case NumberTokens::expsymbol:
						$state = NumberTokens::expsign;
						break;
					default:
						$state = NumberTokens::bad;
						break;
				}
			}
			else if ((substr($this->str, $this->pos, 1) == '.') && ($state == NumberTokens::digit))
			{
				//echo "[" . __LINE__ . "] fraction\n";
				$state = NumberTokens::fraction;
			}
			else if (((substr($this->str, $this->pos, 1) == 'E') || (substr($this->str, $this->pos, 1) == 'e')) && (($state == NumberTokens::digit) || ($state == NumberTokens::fraction)))			
			{
				//echo "[" . __LINE__ . "] exp\n";
				$state = NumberTokens::expsymbol;
			}
			else if (strchr(NEXUSWhiteSpace, substr($this->str, $this->pos, 1)) || strchr (NEXUSPunctuation, substr($this->str, $this->pos, 1)))
			{
				//echo "[" . __LINE__ . "] whitespace, punctuation\n";
				$state = NumberTokens::done;
			}
			else
			{
				//echo "[" . __LINE__ . "] bad\n";
				$state = NumberTokens::bad;
			}
			
			if (($state != NumberTokens::bad) && ($state != NumberTokens::done))
			{
				//echo "[" . __LINE__ . "] OK\n";
				$this->buffer .= substr($this->str, $this->pos, 1);
				$this->pos++;
			}
		}
		//echo "[" . __LINE__ . "] done number\n";
		
		$this->pos--;
		return true; 		
	}
	
	//------------------------------------------------------------------------------------
	function ParseString()
	{
		//echo "[" . __LINE__ . "] ParseString\n";
		$this->buffer = '';
		
		$this->pos++;
		
		$state = StringTokens::ok;
		while ($state != StringTokens::done)
		{
			//echo "[" . __LINE__ . "] --" . $this->str{$this->pos} . "\n";
			
			switch ($state)
			{
				case StringTokens::ok:
					if (substr($this->str, $this->pos, 1) == "'")
					{
						$state = StringTokens::quote;
					}
					else
					{
						$this->buffer .= substr($this->str, $this->pos, 1);
					}
					break;
					
				case StringTokens::quote:
					if (substr($this->str, $this->pos, 1) == "'")
					{
						$this->buffer .= substr($this->str, $this->pos, 1);
						$state = StringTokens::ok;
					}
					else
					{
						$state = StringTokens::done;
						$this->pos--;
					}
					break;
					
				default:
					break;
			}			
			$this->pos++;
		}
		$this->pos--;
		return true;
	}
	

	//------------------------------------------------------------------------------------
	function ParseToken()
	{
		$this->buffer = '';
		
		while (
			($this->pos < strlen($this->str))
			&& (!strchr (NEXUSWhiteSpace, substr($this->str, $this->pos, 1)))
			&& (!strchr (NEXUSPunctuation, substr($this->str, $this->pos, 1)))
			)
		{
			$this->buffer .= substr($this->str, $this->pos, 1);
			$this->pos++;
		}
		$this->pos--;
		return true;
	}
	
}

//----------------------------------------------------------------------------------------
function parse_newick($newick)
{
	// read a tree

	$scanner = new Scanner($newick);
	$token = $scanner->GetToken();

	$state = 0;
	$stack = array();


	$t = new Tree();
	$curnode = $t->NewNode();
	$t->root = $curnode;


	while ($state != 99)
	{
		//echo "[" . __LINE__ . "] state=$state\n";
		//echo "[" . __LINE__ . "] $token " . $scanner->buffer . "\n";
	
		switch ($state)
		{
			case 0: // getname				
				switch ($token)
				{
					case TokenTypes::Number:
					case TokenTypes::QuotedString:
					case TokenTypes::String:
						$label = $scanner->buffer;												
						$curnode->SetLabel($label);
						$t->num_leaves++;
					
						$token = $scanner->GetToken();
						$state = 1;
						break;

					case TokenTypes::OpenPar:
						$state = 2;
						break;
					
					default:
						$state = 99;
						break;
				}
				break;
			
			case 1: // getinternode
				switch ($token)
				{
					case TokenTypes::Colon:
					case TokenTypes::Comma:
					case TokenTypes::ClosePar:
						$state = 2;
						break;
					
					default:
						$state = 99;
						break;
				}
				break;
			
			case 2: // nextmove
				switch ($token)
				{
					case TokenTypes::Colon:							
						$token = $scanner->GetToken();

						$prefix = '';
						
						// negative branch length will have a minus sign
						if ($token == TokenTypes::Minus)
						{
							$prefix = '-';
							$token = $scanner->GetToken();
						}
						
						if ($token == TokenTypes::Number)
						{
							$number = $prefix . number_format($scanner->buffer, 5);
							$curnode->SetAttribute('edge_length', $number);
							$t->has_edge_lengths = true;
							$token = $scanner->GetToken();
						}
						else
						{
							echo "[" . __LINE__ . "] Expecting a number, got " . $scanner->buffer . " instead"; exit();
							$state = 99;
						}
						break;
					
					case TokenTypes::Comma:
						$stack_size = count($stack);
						if ($stack_size == 0)
						{
							// "Tree description unbalanced, this \")\" has no matching \"(\"";
							echo "[" . __LINE__ . "] Tree description unbalanced, this \")\" has no matching \"(\""; exit();
							$state = 99;
						}
						else
						{						
							$q = $t->NewNode();
							$curnode->SetSibling($q);		
							$q->SetAncestor($stack[$stack_size - 1]);
							$curnode = $q;
					
							$token = $scanner->GetToken();
							$state = 0;
						}
						break;	
											
					case TokenTypes::OpenPar:
						$stack[] = $curnode;
						$q = $t->NewNode();
						$curnode->SetChild($q);
						$q->SetAncestor($curnode);
						$curnode = $q;
						$state = 0;
						$token = $scanner->GetToken();
						break;
					
					case TokenTypes::ClosePar:
						if (empty($stack))
						{
							echo "[" . __LINE__ . "] Tree description unbalanced (an extra \")\")"; exit();
							$state = 99;
						}
						else
						{
							$curnode = array_pop($stack);
							$state = 3;
							$token = $scanner->GetToken();
						}
						break;
				
					case TokenTypes::SemiColon:
						if (empty($stack))
						{
							$state = 99;
						}
						else
						{
							echo "[" . __LINE__ . "] Tree description ended prematurely (stack not empty)"; exit();
							$state = 99;
						}
						break;
				
					default:
						$state = 99;
						break;
				}
				break;
		
			case 3: // finishchildren
				switch ($token)
				{
					case TokenTypes::Number:
					case TokenTypes::QuotedString:
					case TokenTypes::String:
						$label = $scanner->buffer;												
						$curnode->SetLabel($label);
						$token = $scanner->GetToken();
						$state = 1;
						break;
		
					case TokenTypes::Colon:							
						$token = $scanner->GetToken();
						
						$prefix = '';
						
						// negative branch length will have a minus sign
						if ($token == TokenTypes::Minus)
						{
							$prefix = '-';
							$token = $scanner->GetToken();
						}
						
						if ($token == TokenTypes::Number)
						{
							$number = $prefix . number_format($scanner->buffer, 5);
							$curnode->SetAttribute('edge_length', $number);
							$t->has_edge_lengths = true;
							$token = $scanner->GetToken();
						}
						else
						{
							echo "[" . __LINE__ . "] expected a number, got " . $scanner->buffer . ' ' . $token; exit();
							$state = 99;
						}
						break;
					
					case TokenTypes::ClosePar:
						if (empty($stack))
						{
							echo "[" . __LINE__ . "] Tree description unbalanced (an extra \")\")"; exit();
							$state = 99;
						}
						else
						{
							$curnode = array_pop($stack);
							$state = 3;
							$token = $scanner->GetToken();
						}
						break;
					
					case TokenTypes::Comma:
						$stack_size = count($stack);
						if ($stack_size == 0)
						{
							echo "[" . __LINE__ . "] Tree description unbalanced, this \")\" has no matching \"(\""; exit();
							$state = 99;
						}
						else
						{			
							$q = $t->NewNode();
							$curnode->SetSibling($q);							
							$q->SetAncestor($stack[$stack_size - 1]);
							$curnode = $q;
					
							$token = $scanner->GetToken();
							$state = 0;
						}
						break;	
					
					case TokenTypes::SemiColon:
						$state = 2;
						break;
					
					default:
						if (empty($stack))
						{
							echo "[" . __LINE__ . "] Tree description unbalanced"; exit();
							$state = 99;
						}
						else
						{
							/*
						errormsg = "Syntax error [FINISHCHILDREN]: expecting one of \":,();\" or internal label, got ";
						errormsg += parser.GetToken();
						errormsg += " instead";
								*/
								
							echo "[" . __LINE__ . "] Syntax error [FINISHCHILDREN]: expecting one of \":,();\" or internal label, got " . $scanner->buffer . " instead"; exit();
							$curnode = array_pop($stack);
							$state = 99;
						}
						break;
				}
		
			default:
				break;
		}
	}
	
	return $t;
}

if (0)
{
	$newick = "(KJ836409.1:0.0394167,((((KJ837499.1:0.000790185,(HQ948094.1:0.005835,((KJ836642.1:0.00223928,(KJ836862.1:1.48198e-07,KJ836538.1:0.00237892):0.000143574):0.000696052,(((KJ838652.1:0,KJ839820.1:7.36166e-05):7.36166e-05,KJ836425.1:0):7.36166e-05,HQ948093.1:0):0.00170478):0.00135383):0.000386304):0.0209252,(((((KT074045.1:0,KJ836515.1:0):0,KJ838226.1:0):0,HM901923.1:0):0.000592874,KJ836448.1:0.00178619):0.000974122,KJ839494.1:0.00140873):0.0347398):0.0326188,(KR786504.1:0.00510067,((FJ582232.1:0,(KJ163559.1:0.00235992,FJ582230.1:0.00481717):8.77903e-05):0.000672437,FJ582231.1:0.00171802):0.00450818):0.0518167):0.0119368,((((((((KX957905.1:0,GU705980.1:0.00478067):1.49689e-05,KJ837790.1:0):1.49689e-05,KJ837713.1:0):0.000689581,(((((((((((((((KX957869.1:0,KX374766.1:5.56418e-09):5.56418e-09,KX957868.1:0):5.56418e-09,KX957867.1:0):5.56418e-09,KX957865.1:0):5.56418e-09,KX957864.1:0):5.56418e-09,KX957863.1:0):5.56418e-09,KX957862.1:0):5.56418e-09,KX957861.1:0):5.56418e-09,KX957860.1:0):5.56418e-09,KX374767.1:0):5.56418e-09,GU705983.1:0):0.000600043,(KX957904.1:0.00178088,((KX374770.1:0,KX957903.1:7.10403e-06):7.10403e-06,GU705978.1:0):0.000602462):0.00178281):0.00060507,KY121842.1:0.0011822):0.00115752,(KX957866.1:0,(((((JQ909849.1:0.00237954,GU705979.1:0):4.78638e-07,KT164634.1:0):4.78638e-07,KT074073.1:0):4.78638e-07,KY121818.1:0):4.78638e-07,KY121823.1:0):4.78638e-07):3.21721e-05):0.000188326,KC709831.1:0):0.00171617):0.0110093,AF250940.1:0.00779681):0.00451422,(AF250941.1:0.0115977,(((((EU726628.1:0,EU726627.1:0):0,EU726626.1:0):0,EU726621.1:0):0,EU726549.1:0):0.00124566,(EU726624.1:0,EU726601.1:0):0.00113908):0.0213067):0.00363395):0.0511406,(((KJ838228.1:0.00238854,HM901915.1:0.00238854):0.000383077,KJ837591.1:0.00200547):0.0398015,((KM568799.1:0,KM562022.1:0):0.0367986,HQ948088.1:0.038264):0.0135556):0.0220487):0.0134001,(KC560283.1:0.00647048,(KY072536.1:0.00206123,((((((((((((((((((KY072692.1:3.09203e-09,KY072384.1:0.00237906):0,KY072512.1:3.09203e-09):0,KY072457.1:3.09203e-09):0,KY072408.1:3.09203e-09):0,KY072378.1:3.09203e-09):0,KY072344.1:3.09203e-09):0,KY072316.1:3.09203e-09):0,KY072314.1:3.09203e-09):0,KY072271.1:3.09203e-09):0,KY072263.1:3.09203e-09):0,KY072262.1:3.09203e-09):0,KY072261.1:3.09203e-09):0,KY072174.1:3.09203e-09):0,KY072146.1:3.09203e-09):0,KP259020.1:3.09203e-09):0,KP259004.1:3.09203e-09):0.00234357,((KY072458.1:0,((KY072490.1:0,KY072495.1:0):0,(KY072601.1:0,KY072662.1:0):0):0):0,((KY072382.1:0,KP259033.1:0):0,KY072383.1:0):0):3.54967e-05):0.000187633,(KY072600.1:0.00236741,((KY072422.1:0,(KY072388.1:0,KY072389.1:0):0):0,((KY072370.1:0,KY072377.1:0):0,(KP259058.1:0,KY072268.1:0):0):0):0):1.16517e-05):0.00220091):0.000322574):0.000708561):0.0266027):0.0069557):0.0394167);";

	//$newick = "(d,e,(c,(a,b)));";
	
	//$newick = file_get_contents('problem trees/phylo.io.1.nwk-fixed');
	//$newick = file_get_contents('legume.tre');
	
	$newick = "((('BBDEC032-09':0.00000,'ASDMT1267-11':0.00000)191:0.00000,('BBDEC029-09':0.00000,'ASDIP553-15':0.00000)192:0.00000)197:0.00000,(('BBDCP883-10':0.00000,'BBDCP882-10':0.00000)193:0.00000,('BBDEC466-09':0.00000,'BBDCP884-10':0.00000)194:0.00000)198:0.00000,(('BBDCP885-10':0.00000,'BBDCP886-10':0.00000)195:0.00000,('BBDCP887-10':0.00000,('BBDEC031-09':0.00002,(('BBDEC030-09':0.00000,'ASDMT1259-11':0.00000)186:0.00002,('BBDEC340-09':0.00001,('BBDCP881-10':0.00002,('ASDIP287-15':0.00008,('AMTPD4637-16':0.00060,((('CNBFB250-15':0.00009,('CNBPA177-12':0.00000,'CNBPD479-12':0.00003)102:0.00002)103:0.00081,('BBDIV1307-12':0.00023,'BARSD020-16':0.00039)131:0.00034)174:0.00012,(((('AFBR033-13':0.00051,(Query:0.00073,('AAASF014-17':0.00007,('AAASF010-17':0.00005,('AAASF012-17':0.00003,((('AAASF025-17':0.00002,('AAASF021-17':0.00002,('AAASF013-17':0.00000,('AAASF023-17':0.00006,'AAASF024-17':-0.00001)133:0.00000)134:0.00001)136:0.00001)137:0.00002,('AAASF027-17':0.00000,'AAASF020-17':0.00000)138:0.00001)142:0.00003,('AAASF009-17':0.00005,('AAASF011-17':0.00009,(('AAASF022-17':0.00000,'AAASF005-17':0.00000)140:0.00001,('AAASF028-17':0.00002,(('AAASF006-17':0.00003,('AAASF004-17':-0.00001,'AAASF008-17':0.00005)132:0.00001)135:0.00003,('AAASF007-17':0.00000,'AAASF026-17':0.00002)139:0.00000)141:0.00000)143:0.00001)144:0.00001)145:0.00001)146:0.00001)147:0.00000)148:0.00001)149:0.00002)150:0.00031)151:0.00002)152:0.00020,('BCMS470-12':0.00084,(('AAASF015-17':0.00003,'AAASF029-17':0.00005)104:0.00002,('AAASF030-17':0.00000,('AAASF032-17':0.00000,'AAASF019-17':0.00001)105:0.00000)106:0.00001)107:0.00080)169:0.00010)176:0.00004,('BBDIV1362-12':0.00090,'ASPNF082-12':0.00085)177:0.00002)180:0.00005,('BBDEC655-09':0.00083,(('AMTPD2513-15':0.00068,('CNBPK408-13':0.00015,('CNBPB004-12':0.00000,'CNBPA098-12':0.00000)118:0.00010)119:0.00054)170:0.00013,(('AGAKF121-17':0.00051,(('AMTPF2654-16':0.00003,('AMTPF2624-16':0.00000,('AMTPF2575-16':0.00000,'AMTPF2621-16':0.00000)120:0.00000)121:0.00002)122:0.00043,('BBDCN981-10':0.00002,((('CNBFK145-15':0.00000,('CNBFC208-15':0.00001,('CNBFL251-15':0.00003,'CNBFD298-15':0.00000)123:0.00003)124:0.00001)125:0.00004,('CNBAB012-12':0.00000,'CNBAA016-12':0.00000)126:0.00003)127:0.00004,('CNBFL512-15':0.00004,'CNBAA015-12':0.00002)128:0.00002)129:0.00003)130:0.00045)166:0.00004)167:0.00016,(((('ACHAR2573-18':0.00001,'CCHAR2082-19':0.00012)158:0.00002,('ACHAR009-18':0.00000,('ACHAR014-18':0.00000,('ACHAR011-18':0.00000,(('ACHAR017-18':0.00000,('BCHAR1205-18':0.00000,(('ACHAR2205-18':0.00000,'ACHAR013-18':0.00000)153:0.00000,('ACHAR012-18':0.00000,'ACHAR026-18':0.00000)154:0.00000)155:0.00000)156:0.00000)157:0.00000,('BCHAR2737-18':0.00000,('ACHAR018-18':0.00000,'ACHAR023-18':0.00000)159:0.00000)160:0.00000)161:0.00000)162:0.00000)163:0.00001)164:0.00001)165:0.00038,('BBDIV1282-12':0.00058,'AMCAS288-21':0.00073)171:0.00003)172:0.00004,(('AMTPF2974-16':0.00000,'AMTPF3496-16':0.00003)116:0.00082,('BBDCQ709-10':0.00041,('BBDIP156-09':0.00002,((('AGAKL1518-17':0.00000,'AGAKK266-17':0.00000)109:0.00000,('AGAKK041-17':0.00000,'AGAKK012-17':0.00000)110:0.00000)113:0.00000,(('AGAKH287-17':0.00000,'AGAKK278-17':0.00000)111:0.00000,('AGAKH256-17':0.00000,('AGAKH265-17':0.00000,'AGAKK310-17':0.00000)108:0.00000)112:0.00000)114:0.00000)115:0.00019)117:0.00071)168:0.00010)173:0.00003)175:0.00003)178:0.00001)179:0.00005)181:0.00002)182:0.00003)183:0.00028)184:0.00021)185:0.00003)187:0.00000)188:0.00000)189:0.00000)190:0.00000)196:0.00000)199:0.00000)200;";

	$t = parse_newick($newick);

	echo $t->WriteNewick() . "\n";
	
	$n = new NodeIterator ($t->root);
	$a = $n->Begin();
	while ($a != NULL)
	{
		
		echo $a->GetLabel() . " " . $a->GetAttribute('edge_length') . "\n";
		$a = $n->Next();
	}


	
}



?>
