# BOLD Examples to explore

## Type specimens

This will be a challenge as often type sequences are short, and these don’t work well with k-mer based vector searches as the vectors for a subsequence of a larger sequence will often look rather different.

Types are poorly flagged in the BOLD data packages. There are some datasets that aggregate them, e.g. [DS-SATYP1](https://bold-view-bf2dfe9b0db3.herokuapp.com/recordset/DS-SATYP1)

### SAMPA1374-19 Solus parvifenestrata

[](https://bold-view-bf2dfe9b0db3.herokuapp.com/record/SAMPA1374-19)

```
AACTTTATACTTCATTTTTGGTATTTGAGCNGGAATAGTAGGAACTTCACTAAGATTACT
AATTCGAGCAGAATTAGGAACTCCTGGTTCTTTAATTGGAGATGATCAAATTTATAACAC
TATTGTGACA
```
 



## DS-ELADIS

DNA barcodes from type specimens https://doi/org/10.1111/1755-0998.12361 https://pmc.ncbi.nlm.nih.gov/articles/PMC4964951/

Interesting discussion of linking types to BINs, and a tool called BLOG https://pmc.ncbi.nlm.nih.gov/articles/PMC2775153/



## GBAAW82677-24 [no insdc_acs, bad taxonomy]

[GBAAW82677-24](https://bold-view-bf2dfe9b0db3.herokuapp.com/record/GBAAW82677-24) “Mined from GenBank, NCBI” but no `insdc_acs` value, but does have `sampleid` of `OQ553230` which is in GenBank [OQ553230](https://www.ncbi.nlm.nih.gov/nuccore/OQ553230) from _Jaydia truncata_ with publication doi:https://doi.org/10.1002/ece3.10822 https://pmc.ncbi.nlm.nih.gov/articles/PMC10711522/. BOLD has this as identified to “Chordata” and it clusters with BIN [BOLD:AAF8217](https://bold-view-bf2dfe9b0db3.herokuapp.com/bin/BOLD:AAF8217) but is not part of the BIN in BOLD.

BOLD:AAF8217 has multiple taxonomic labels, would be a nice example to explore.

## ABLCV357-09 also in GBIF but errors in specimen codes

ABLCV357-09 has `sampleid` CSU-CPG-LEP002307, there is a GBIF record https://www.gbif.org/occurrence/2432301357 with `catalogNumber` CSU_ENT1055698 that matches this record. Collection dates are similar, `2004-09-12` and `2004-09-11`, but note that verbatim date is a range.

However 2432301357 has occurrence remarks:

> Barcode of Life DNA Voucher Specimen CSU_CPG_LEP002307 BOLD ID ABLCU357-09.

Note the “U” not “V”. However record ABLCU357-09 says “CSU-CPG-LEP001357”, so something is wrong.

## BOLD:AAJ4577 complicated taxonomy in paper

BOLD:AAJ4577 has many names, but see “On the identity of Hesperia parrhasius Fabricius, 1793 and its allied species (Lepidoptera: Lycaenidae)” https://doi.org/10.1016/j.aspen.2023.102165 open access for details.

## The InBIO Barcoding Initiative Database: contribution to the knowledge on DNA barcodes of Iberian Plecoptera

https://doi.org/10.3897/BDJ.8.e55137 some authors have ORCIDs, e.g. José Manuel Tierno de Figueroa https://orcid.org/0000-0003-1616-9815 who has also identified specimens, e.g. https://www.gbif.org/occurrence/2819134364 (as J.M. Tierno de Figueroa) (= IBIPP092-20), this dataset is also in Bionomia with no attributions (yet) https://bionomia.net/dataset/50942caf-a62e-44c7-9998-e2a949aa85b2

## Barcodes without geo but geo in GBIF 

### GBMND37875-21

GBMND37875-21 has accession LC582909, which has no geotagging https://getentry.ddbj.nig.ac.jp/getentry/na/LC582909/?filetype=html but the same record is in GBIF https://www.gbif.org/occurrence/3043786337 with coordinates. Database is https://www.gbif.org/dataset/32de56e2-db99-4f9a-bc23-54d629013809 see also https://www.nies.go.jp/ogasawara/#/

### GACO4502-19

GACO4502-19 is WAMT138936 which is geotagged in GBIF https://www.gbif.org/occurrence/1935804028



## Liptena durbania nice paper linked to by GenBank

https://bold-view-bf2dfe9b0db3.herokuapp.com/bin/BOLD:ADM0459, e.g. OQ740716 “Taxonomic notes on iLiptena durbania/i Bethune-Baker, 1915 (Papilionoidea: Lycaenidae: Poritiinae)”
[doi:10.4314/met.v34i1.3](https://doi.org/10.4314/met.v34i1.3)

Nice pics of specimens, potential links between paper, specimens, BOLD, etc.

## Barcodes identified in GenBank but not BOLD

GBCOC5986-23 is “Chordata” in BOLD, but Gloydius liupanensis in Genbank https://www.ebi.ac.uk/ena/browser/view/OQ416193. Why the difference?

## Barcodes submitted to Genbank and GBIF, mined by BOLD, “DNA barcoding of black flies (Diptera: Simuliidae) in Indonesia”

Article https://doi.org/10.1186/s13071-023-05875-1, data in GBIF  https://www.gbif.org/dataset/c80987f7-f87a-4ae3-a2cc-ccd59bc951e8 (https://doi.org/10.15468/h5jh4z), accessions OQ117897–OQ117982 (listed in https://doi.org/10.1186/s13071-023-05875-1).

Lots of incomplete links, can we connect? BIN is quite messy https://bold-view-bf2dfe9b0db3.herokuapp.com/bin/BOLD:AAH9537

GBIF dataset has additional sequences, e.g. https://www.gbif.org/occurrence/4010748333 which is https://www.ncbi.nlm.nih.gov/nuccore/MW548991, GBIF record has link to paper (see both web page and raw data):

```
"extensions": {
    "http://rs.gbif.org/terms/1.0/DNADerivedData": [
      {
        "amplificationReactionVolume": "20",
        "amplificationReactionVolumeUnit": "µl",
        "nucl_acid_amp": "https://doi.org/10.1016/j.actatropica.2021.105889",
        "pcr_cond": "initial denaturation:94_2,94_0.5;annealing:50_0.75;elongation:72_0.75;final elongation:72_5;40",
        "pcr_primer_name_forward": "LCO1490",
        "pcr_primer_name_reverse": "HCO2198",
        "pcr_primer_reference": "Folmer, O., Black, M., Hoeh, W., Lutz, R., & Vrijenhoek, R. (1994). DNA primers for amplification of mitochondrial cytochrome c oxidase subunit I from diverse metazoan invertebrates. Molecular marine biology and biotechnology, 3(5), 294–299.",
        "target_gene": "COI"
      }
    ]
  },
```



 