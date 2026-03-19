# Research

## Testing retrieval of BINs

To test the accuracy of sequence search using k-mers we use `search_test.php` to get a random barcode from within a BIN, search for the most similar sequences, and recording how many of the BIN sequences we correctly retrieve.

Following chat with Claude Code I tried two values for `ivfflat.probes` 1, and 40. 1 is the default and wat the web interface uses. Setting the value to 40, the search takes about 20 times longer.

```
pg_query($db, 'SET ivfflat.probes = 1');
```