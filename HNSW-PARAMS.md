# HNSW Parameter Reference

This document shows the HNSW parameters (`m` and `efConstruction`) generated for different accuracy levels and dimensions.

## Design Philosophy

- **Sublinear Scaling**: Uses `sqrt(dimensions/256)` instead of linear scaling
- **Conservative Caps**: `m` capped at 64, `efConstruction` at 500
- **Balanced Approach**: Optimized for search quality vs indexing speed trade-off

## Parameter Tables

### 128 Dimensions
| Level | Strategy     | m  | efConstruction | Index Time | Search Quality |
|-------|-------------|-----|----------------|------------|----------------|
| 1     | Concatenate | 8   | 42             | Fastest    | Minimal        |
| 2     | Average     | 11  | 71             | Very Fast  | Low            |
| 3     | Average     | 17  | 106            | Fast       | Moderate       |
| 4     | Average     | 23  | 141            | Medium     | Good           |
| 5     | Average     | 28  | 212            | Slow       | High           |
| 6     | Average     | 34  | 283            | Slower     | Very High      |
| 7     | ScriptScore | -   | -              | Slowest    | Exact          |

### 256 Dimensions (Base)
| Level | Strategy     | m  | efConstruction | Index Time | Search Quality |
|-------|-------------|-----|----------------|------------|----------------|
| 1     | Concatenate | 12  | 60             | Fastest    | Minimal        |
| 2     | Average     | 16  | 100            | Very Fast  | Low            |
| 3     | Average     | 24  | 150            | Fast       | Moderate       |
| 4     | Average     | 32  | 200            | Medium     | Good           |
| 5     | Average     | 40  | 300            | Slow       | High           |
| 6     | Average     | 48  | 400            | Slower     | Very High      |
| 7     | ScriptScore | -   | -              | Slowest    | Exact          |

### 384 Dimensions
| Level | Strategy     | m  | efConstruction | Index Time | Search Quality |
|-------|-------------|-----|----------------|------------|----------------|
| 1     | Concatenate | 15  | 74             | Fastest    | Minimal        |
| 2     | Average     | 20  | 123            | Very Fast  | Low            |
| 3     | Average     | 30  | 185            | Fast       | Moderate       |
| 4     | Average     | 39  | 247            | Medium     | Good           |
| 5     | Average     | 49  | 370            | Slow       | High           |
| 6     | Average     | 59  | 493            | Slower     | Very High      |
| 7     | ScriptScore | -   | -              | Slowest    | Exact          |

### 1536 Dimensions (OpenAI ada-002)
| Level | Strategy     | m  | efConstruction | Index Time | Search Quality |
|-------|-------------|-----|----------------|------------|----------------|
| 1     | Concatenate | 29  | 147            | Fastest    | Minimal        |
| 2     | Average     | 39  | 245            | Very Fast  | Low            |
| 3     | Average     | 59  | 367            | Fast       | Moderate       |
| 4     | Average     | 64* | 490            | Medium     | Good           |
| 5     | Average     | 64* | 500*           | Slow       | High           |
| 6     | Average     | 64* | 500*           | Slower     | Very High      |
| 7     | ScriptScore | -   | -              | Slowest    | Exact          |

*Values capped at maximum

### 3072 Dimensions
| Level | Strategy     | m  | efConstruction | Index Time | Search Quality |
|-------|-------------|-----|----------------|------------|----------------|
| 1     | Concatenate | 42  | 208            | Fastest    | Minimal        |
| 2     | Average     | 55  | 346            | Very Fast  | Low            |
| 3     | Average     | 64* | 500*           | Fast       | Moderate       |
| 4     | Average     | 64* | 500*           | Medium     | Good           |
| 5     | Average     | 64* | 500*           | Slow       | High           |
| 6     | Average     | 64* | 500*           | Slower     | Very High      |
| 7     | ScriptScore | -   | -              | Slowest    | Exact          |

*Values capped at maximum

## Comparison with Industry Defaults

- **Elasticsearch**: m=16, efConstruction=100
- **Pinecone**: m=16, efConstruction=200
- **Sigmie Level 3**: m=24, efConstruction=150 (balanced default)
- **Sigmie Level 6**: m=48-64, efConstruction=400-500 (premium quality)

## Recommendations

- **Small datasets (<10K vectors)**: Use Level 1-3
- **Medium datasets (10K-100K)**: Use Level 3-4
- **Large datasets (>100K)**: Use Level 4-6
- **Critical applications**: Use Level 7 (exact search, no HNSW)
- **Real-time applications**: Avoid Level 7 (too slow)

## Performance Notes

- **m** controls graph density: Higher = better recall, slower indexing
- **efConstruction** controls build quality: Higher = better graph structure, slower indexing
- **Sublinear scaling** prevents extreme values at high dimensions
- **Level 7** uses exact (brute-force) search with no HNSW index
