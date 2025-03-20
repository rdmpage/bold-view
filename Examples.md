# BOLD Examples to explore

## GBAAW82677-24 [no insdc_acs, bad taxonomy]

[GBAAW82677-24](https://bold-view-bf2dfe9b0db3.herokuapp.com/record/GBAAW82677-24) “Mined from GenBank, NCBI” but no `insdc_acs` value, but does have `sampleid` of `OQ553230` which is in GenBank [OQ553230](https://www.ncbi.nlm.nih.gov/nuccore/OQ553230) from _Jaydia truncata_ with publication doi:https://doi.org/10.1002/ece3.10822 https://pmc.ncbi.nlm.nih.gov/articles/PMC10711522/. BOLD has this as identified to “Chordata” and it clusters with BIN [BOLD:AAF8217](https://bold-view-bf2dfe9b0db3.herokuapp.com/bin/BOLD:AAF8217) but is not part of the BIN in BOLD.

BOLD:AAF8217 has multiple taxonomic labels, would be a nice example to explore.

## ABLCV357-09 also in GBIF but errors in specimen codes

ABLCV357-09 has `sampleid` CSU-CPG-LEP002307, there is a GBIF record https://www.gbif.org/occurrence/2432301357 with `catalogNumber` CSU_ENT1055698 that matches this record. Collection dates are similar, `2004-09-12` and `2004-09-11`, but note that verbatim date is a range.

However 2432301357 has occurrence remarks:

> Barcode of Life DNA Voucher Specimen CSU_CPG_LEP002307 BOLD ID ABLCU357-09.

Note the “U” not “V”. However record ABLCU357-09 says “CSU-CPG-LEP001357”, so something is wrong.

 