<?php

class CM_Usertext_Filter_BadwordsTest extends CMTest_TestCase {

	public function testProcess() {
		$replace = '…';
		$badwords = new CM_Paging_ContentList_Badwords();
		$filter = new CM_Usertext_Filter_Badwords();
		$render = new CM_Render();

		$actual = $filter->transform("hello foo there", $render);
		$this->assertSame("hello foo there", $actual);

		$badwords->add('foo');
		$badwords->add('x … x');
		$badwords->add('f(o-].)o');
		$badwords->add('bar');
		$badwords->add('foobar');
		$badwords->add('zoo*far');
		CMTest_TH::clearCache();

		$actual = $filter->transform("hello foo there", $render);
		$this->assertSame("hello ${replace} there", $actual);
		$actual = $filter->transform("hello x foo x there", $render);
		$this->assertSame("hello ${replace} there", $actual);
		$actual = $filter->transform("hello Foo there", $render);
		$this->assertSame("hello ${replace} there", $actual);
		$actual = $filter->transform("hello foot there", $render);
		$this->assertSame("hello ${replace} there", $actual);

		$actual = $filter->transform("hello f(o-].)o there", $render);
		$this->assertSame("hello ${replace} there", $actual);

		$actual = $filter->transform("hello bar there", $render);
		$this->assertSame("hello ${replace} there", $actual);
		$actual = $filter->transform("hello bart there", $render);
		$this->assertSame("hello ${replace} there", $actual);
		$actual = $filter->transform("hello bar3 there", $render);
		$this->assertSame("hello ${replace} there", $actual);
		$actual = $filter->transform("hello bartender there", $render);
		$this->assertSame("hello ${replace} there", $actual);
		$actual = $filter->transform("hello bar.de there", $render);
		$this->assertSame("hello ${replace} there", $actual);
		$actual = $filter->transform("hello bar. there", $render);
		$this->assertSame("hello ${replace} there", $actual);

		$actual = $filter->transform("hello foobar there", $render);
		$this->assertSame("hello ${replace} there", $actual);
		$actual = $filter->transform("hello XfoobarX there", $render);
		$this->assertSame("hello ${replace} there", $actual);
		$actual = $filter->transform("hello mayo.foobar.ran there", $render);
		$this->assertSame("hello ${replace} there", $actual);

		$actual = $filter->transform("hello zoofar there", $render);
		$this->assertSame("hello ${replace} there", $actual);

		$actual = $filter->transform("hello zoo!!far there", $render);
		$this->assertSame("hello ${replace} there", $actual);
		$actual = $filter->transform("hello zoo far there", $render);
		$this->assertSame("hello ${replace} there", $actual);
	}
}
