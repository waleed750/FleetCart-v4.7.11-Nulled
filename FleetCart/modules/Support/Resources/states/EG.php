<?php

// Egyptian governorates (ISO 3166-2:EG). Names follow the active locale; the
// stored value is the code. Source of truth: Modules\Egypt\Services\GovernorateList.
return \Modules\Egypt\Services\GovernorateList::names(app()->getLocale());
